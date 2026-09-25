<?php

declare(strict_types=1);

namespace Jengo\Base\Tooling\Modifier;

use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as StandardPrinter;
use RuntimeException;
use InvalidArgumentException;

class ClassModifier
{
    private string $code;
    /** @var Node[] */
    private array $ast;
    private NodeFinder $finder;
    private ?Class_ $targetClass = null;
    private ?Namespace_ $namespaceNode = null;

    private function __construct(string $code)
    {
        $this->code = $code;
        $parser = (new ParserFactory())->createForHostVersion();
        $this->ast = $parser->parse($code) ?? [];
        $this->finder = new NodeFinder();

        // Target the first namespace node if present
        $this->namespaceNode = $this->finder->findFirstInstanceOf($this->ast, Namespace_::class);

        // Target the first class defined in the AST
        /** @var Class_|null $class */
        $class = $this->finder->findFirstInstanceOf($this->ast, Class_::class);
        $this->targetClass = $class;

        if (! $this->targetClass) {
            throw new RuntimeException("No class declaration found in the provided code.");
        }
    }

    public static function fromFile(string $filePath): self
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new InvalidArgumentException("File not found or unreadable: {$filePath}");
        }

        return new self(file_get_contents($filePath));
    }

    public static function fromString(string $code): self
    {
        return new self($code);
    }

    /**
     * Add a top-level `use Full\Namespace\ClassName;` statement if it doesn't already exist.
     */
    public function addUseStatement(string $targetClass, ?string $alias = null): self
    {
        $targetClass = ltrim($targetClass, '\\');
        $existingUses = $this->finder->findInstanceOf($this->ast, UseUse::class);

        foreach ($existingUses as $use) {
            if ($use->name->toString() === $targetClass) {
                if ($alias === null && $use->alias === null) {
                    return $this;
                }
                if ($alias !== null && $use->alias?->toString() === $alias) {
                    return $this;
                }
            }
        }

        $useUse = new UseUse(
            new Name($targetClass),
            $alias !== null ? new Identifier($alias) : null
        );
        $useStmt = new Use_([$useUse]);

        if ($this->namespaceNode !== null) {
            // Find insertion point after existing use statements in namespace
            $stmts = &$this->namespaceNode->stmts;
            $lastUseIndex = -1;
            foreach ($stmts as $i => $stmt) {
                if ($stmt instanceof Use_) {
                    $lastUseIndex = $i;
                }
            }
            if ($lastUseIndex >= 0) {
                array_splice($stmts, $lastUseIndex + 1, 0, [$useStmt]);
            } else {
                array_unshift($stmts, $useStmt);
            }
        } else {
            // Global scope
            $lastUseIndex = -1;
            foreach ($this->ast as $i => $stmt) {
                if ($stmt instanceof Use_) {
                    $lastUseIndex = $i;
                }
            }
            if ($lastUseIndex >= 0) {
                array_splice($this->ast, $lastUseIndex + 1, 0, [$useStmt]);
            } else {
                array_unshift($this->ast, $useStmt);
            }
        }

        return $this;
    }

    /**
     * Updates an existing property's default value, or creates it if missing.
     */
    public function upsertProperty(string $name, mixed $defaultValue, string $visibility = 'public'): self
    {
        $property = $this->findProperty($name);
        $defaultValueNode = $this->convertValueToNode($defaultValue);
        $flags = $this->getVisibilityFlag($visibility);

        if ($property !== null) {
            $property->flags = $flags;
            $property->props[0]->default = $defaultValueNode;
        } else {
            $newProp = new Property($flags, [
                new PropertyProperty(new VarLikeIdentifier($name), $defaultValueNode),
            ]);
            array_unshift($this->targetClass->stmts, $newProp);
        }

        return $this;
    }

    /**
     * Mutates an associative array property by deeply merging or replacing keys while preserving AST nodes and comments.
     */
    public function mutateArrayProperty(string $name, callable $mutator): self
    {
        $property = $this->findProperty($name);

        if (! $property) {
            $this->upsertProperty($name, []);
            $property = $this->findProperty($name);
        }

        $defaultNode = $property->props[0]->default;

        if (! ($defaultNode instanceof Array_)) {
            $defaultNode = new Array_([], ['kind' => Array_::KIND_SHORT]);
            $property->props[0]->default = $defaultNode;
        }

        $currentData = $this->extractArrayDataWithNodes($defaultNode);

        // Pass existing array to user callback: returns modified array
        $modifiedData = $mutator($currentData);

        // Reconstruct AST Array node preserving original item AST nodes, comments, and ClassConstFetch
        $property->props[0]->default = $this->reconstructArrayNode($defaultNode, $modifiedData);

        return $this;
    }

    /**
     * Injects code statements at the start or end of an existing method.
     */
    public function wrapMethod(string $methodName, ?string $prependCode = null, ?string $appendCode = null): self
    {
        $method = $this->findMethod($methodName);
        if (! $method) {
            throw new InvalidArgumentException("Method '{$methodName}' not found.");
        }

        $parser = (new ParserFactory())->createForHostVersion();

        if ($prependCode) {
            $prependStmts = $parser->parse("<?php {$prependCode}") ?? [];
            $method->stmts = array_merge($prependStmts, $method->stmts ?? []);
        }

        if ($appendCode) {
            $appendStmts = $parser->parse("<?php {$appendCode}") ?? [];
            $method->stmts = array_merge($method->stmts ?? [], $appendStmts);
        }

        return $this;
    }

    /**
     * Replaces or adds a method completely from raw PHP code.
     */
    public function upsertMethod(string $methodCode): self
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse("<?php class Dummy { {$methodCode} }");

        /** @var ClassMethod|null $newMethod */
        $newMethod = $this->finder->findFirstInstanceOf($ast ?? [], ClassMethod::class);
        if (! $newMethod) {
            throw new InvalidArgumentException("Invalid method code provided.");
        }

        $existing = $this->findMethod($newMethod->name->toString());
        if ($existing) {
            $existing->flags = $newMethod->flags;
            $existing->params = $newMethod->params;
            $existing->returnType = $newMethod->returnType;
            $existing->stmts = $newMethod->stmts;
        } else {
            $this->targetClass->stmts[] = $newMethod;
        }

        return $this;
    }

    public function addInterface(string $interfaceName): self
    {
        $interfaceName = ltrim($interfaceName, '\\');
        foreach ($this->targetClass->implements as $existing) {
            if ($existing->toString() === $interfaceName) {
                return $this;
            }
        }
        $this->targetClass->implements[] = new FullyQualified($interfaceName);
        return $this;
    }

    public function addTrait(string $traitName): self
    {
        $traitName = ltrim($traitName, '\\');
        $useStmt = new TraitUse([new FullyQualified($traitName)]);
        array_unshift($this->targetClass->stmts, $useStmt);
        return $this;
    }

    public function render(): string
    {
        $printer = new StandardPrinter();
        return $printer->prettyPrintFile($this->ast);
    }

    public function saveTo(string $filePath): void
    {
        file_put_contents($filePath, $this->render());
    }

    private function findProperty(string $name): ?Property
    {
        /** @var Property[] $properties */
        $properties = $this->finder->findInstanceOf($this->targetClass->stmts, Property::class);
        foreach ($properties as $prop) {
            if ($prop->props[0]->name->toString() === $name) {
                return $prop;
            }
        }
        return null;
    }

    private function findMethod(string $name): ?ClassMethod
    {
        /** @var ClassMethod[] $methods */
        $methods = $this->finder->findInstanceOf($this->targetClass->stmts, ClassMethod::class);
        foreach ($methods as $method) {
            if ($method->name->toString() === $name) {
                return $method;
            }
        }
        return null;
    }

    private function getVisibilityFlag(string $visibility): int
    {
        return match (strtolower($visibility)) {
            'protected' => Class_::MODIFIER_PROTECTED,
            'private'   => Class_::MODIFIER_PRIVATE,
            default     => Class_::MODIFIER_PUBLIC,
        };
    }

    /**
     * Extract array data while resolving strings and keeping node info.
     */
    private function extractArrayDataWithNodes(Array_ $arrayNode): array
    {
        $result = [];

        foreach ($arrayNode->items as $item) {
            if ($item === null) {
                continue;
            }

            $key = $this->resolveArrayKey($item->key);
            $val = $this->resolveArrayValue($item->value);

            if ($key !== null) {
                $result[$key] = $val;
            } else {
                $result[] = $val;
            }
        }

        return $result;
    }

    /**
     * Resolve string/int key from AST.
     */
    private function resolveArrayKey(?Expr $keyNode): string|int|null
    {
        if ($keyNode === null) {
            return null;
        }

        if ($keyNode instanceof String_) {
            return $keyNode->value;
        }

        if ($keyNode instanceof LNumber) {
            return $keyNode->value;
        }

        $printer = new StandardPrinter();
        return $printer->prettyPrintExpr($keyNode);
    }

    /**
     * Resolve PHP scalar / class-string representation from AST Expr.
     */
    private function resolveArrayValue(Expr $valNode): mixed
    {
        if ($valNode instanceof String_) {
            return $valNode->value;
        }

        if ($valNode instanceof LNumber || $valNode instanceof DNumber) {
            return $valNode->value;
        }

        if ($valNode instanceof ConstFetch) {
            $name = strtolower($valNode->name->toString());
            return match ($name) {
                'true'  => true,
                'false' => false,
                'null'  => null,
                default => $valNode->name->toString(),
            };
        }

        if ($valNode instanceof ClassConstFetch && $valNode->name instanceof Identifier && $valNode->name->toLowerString() === 'class') {
            if ($valNode->class instanceof Name) {
                return $valNode->class->toString();
            }
        }

        if ($valNode instanceof Array_) {
            return $this->extractArrayDataWithNodes($valNode);
        }

        // Fallback: evaluate expression safely or print
        $printer = new StandardPrinter();
        return $printer->prettyPrintExpr($valNode);
    }

    /**
     * Reconstruct an Array_ AST node preserving original nodes, comments, and converting class-strings to ClassConstFetch.
     */
    private function reconstructArrayNode(Array_ $origArrayNode, array $modifiedData): Array_
    {
        $newItems = [];
        $isAssoc = array_keys($modifiedData) !== range(0, count($modifiedData) - 1);

        // Build index of original items
        $origItemMap = [];
        $origSequentialItems = [];
        $seqIndex = 0;

        foreach ($origArrayNode->items as $origItem) {
            if ($origItem === null) {
                continue;
            }
            $origKey = $this->resolveArrayKey($origItem->key);
            if ($origKey !== null) {
                $origItemMap[(string) $origKey] = $origItem;
            } else {
                $origSequentialItems[$seqIndex++] = $origItem;
            }
        }

        $currSeqIndex = 0;

        foreach ($modifiedData as $key => $value) {
            $keyNode = $isAssoc ? new String_((string) $key) : null;
            $existingItem = $isAssoc
                ? ($origItemMap[(string) $key] ?? null)
                : ($origSequentialItems[$currSeqIndex++] ?? null);

            $valNode = $this->buildValueNode($value, $existingItem?->value);

            $itemNode = new ArrayItem($valNode, $keyNode);

            // Preserve comments from original item if unchanged or relevant
            if ($existingItem !== null && $existingItem->hasAttribute('comments')) {
                $itemNode->setAttribute('comments', $existingItem->getAttribute('comments'));
            }

            $newItems[] = $itemNode;
        }

        // Preserve any comments attached to original array node itself
        $newArray = new Array_($newItems, ['kind' => Array_::KIND_SHORT]);
        if ($origArrayNode->hasAttribute('comments')) {
            $newArray->setAttribute('comments', $origArrayNode->getAttribute('comments'));
        }

        return $newArray;
    }

    /**
     * Convert PHP value to AST Expr node, preserving ClassConstFetch if original was ClassConstFetch or class name.
     */
    private function buildValueNode(mixed $val, ?Expr $existingNode = null): Expr
    {
        // 1. If existing node was a ClassConstFetch and value matches the class name or short name
        if ($existingNode instanceof ClassConstFetch && is_string($val)) {
            $existingClass = $existingNode->class->toString();
            if ($val === $existingClass || str_ends_with($val, '\\' . $existingClass)) {
                return $existingNode;
            }
        }

        // 2. If it's a nested array, reconstruct recursively
        if (is_array($val)) {
            $existingArray = $existingNode instanceof Array_ ? $existingNode : new Array_([], ['kind' => Array_::KIND_SHORT]);
            return $this->reconstructArrayNode($existingArray, $val);
        }

        // 3. If string looks like a valid PHP class identifier or class-string (e.g. CSRF::class, App\Filters\Inertia, MyClass)
        if (is_string($val)) {
            if ($this->isClassString($val)) {
                return $this->createClassConstFetchNode($val);
            }
            return new String_($val);
        }

        if (is_int($val)) {
            return new LNumber($val);
        }

        if (is_float($val)) {
            return new DNumber($val);
        }

        if (is_bool($val)) {
            return new ConstFetch(new Name($val ? 'true' : 'false'));
        }

        if (is_null($val)) {
            return new ConstFetch(new Name('null'));
        }

        return $this->convertValueToNode($val);
    }

    /**
     * Determine if a string is a class name (e.g. contains backslash and ends with PascalCase, or single PascalCase class).
     */
    private function isClassString(string $val): bool
    {
        // Must contain valid PHP identifier characters
        if (! preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*$/', $val)) {
            return false;
        }

        // Extract final class segment
        $segments = explode('\\', $val);
        $lastSegment = end($segments);

        // A class name's final segment must start with uppercase letter (PascalCase)
        // This avoids converting namespaced helper files or snake_case/lowercase paths to ::class
        return preg_match('/^[A-Z][A-Za-z0-9_]*$/', $lastSegment) === 1;
    }

    /**
     * Create ClassConstFetch node (e.g. CSRF::class or \App\Filters\HandleInertiaRequests::class).
     */
    private function createClassConstFetchNode(string $className): ClassConstFetch
    {
        if (str_starts_with($className, '\\')) {
            $nameNode = new FullyQualified(ltrim($className, '\\'));
        } elseif (str_contains($className, '\\')) {
            $nameNode = new FullyQualified($className);
        } else {
            $nameNode = new Name($className);
        }

        return new ClassConstFetch($nameNode, new Identifier('class'));
    }

    private function convertValueToNode(mixed $val): Expr
    {
        if (is_array($val)) {
            $items = [];
            $isAssoc = array_keys($val) !== range(0, count($val) - 1);
            foreach ($val as $k => $v) {
                $items[] = new ArrayItem(
                    $this->convertValueToNode($v),
                    $isAssoc ? new String_((string) $k) : null
                );
            }
            return new Array_($items, ['kind' => Array_::KIND_SHORT]);
        }

        if (is_string($val)) {
            if ($this->isClassString($val)) {
                return $this->createClassConstFetchNode($val);
            }
            return new String_($val);
        }

        if (is_int($val)) {
            return new LNumber($val);
        }

        if (is_float($val)) {
            return new DNumber($val);
        }

        if (is_bool($val)) {
            return new ConstFetch(new Name($val ? 'true' : 'false'));
        }

        if (is_null($val)) {
            return new ConstFetch(new Name('null'));
        }

        throw new InvalidArgumentException("Unsupported value type for AST mapping.");
    }
}