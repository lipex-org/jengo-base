<?php

declare(strict_types=1);

namespace Jengo\Base\Tooling\Modifier;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as StandardPrinter;

class ClassModifier
{
    private string $code;
    private array $ast;
    private NodeFinder $finder;
    private ?Class_ $targetClass = null;

    private function __construct(string $code)
    {
        $this->code = $code;
        $parser = (new ParserFactory())->createForHostVersion();
        $this->ast = $parser->parse($code) ?? [];
        $this->finder = new NodeFinder();

        // Target the first class defined in the AST
        /** @var Class_|null $class */
        $class = $this->finder->findFirstInstanceOf($this->ast, Class_::class);
        $this->targetClass = $class;

        if (!$this->targetClass) {
            throw new \RuntimeException("No class declaration found in the provided code.");
        }
    }

    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException("File not found or unreadable: {$filePath}");
        }

        return new self(file_get_contents($filePath));
    }

    public static function fromString(string $code): self
    {
        return new self($code);
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
            // Update existing property
            $property->flags = $flags;
            $property->props[0]->default = $defaultValueNode;
        } else {
            // Create and append property to top of class statements
            $newProp = new Property($flags, [
                new Node\Stmt\PropertyProperty(new Node\VarLikeIdentifier($name), $defaultValueNode),
            ]);
            array_unshift($this->targetClass->stmts, $newProp);
        }

        return $this;
    }

    /**
     * Mutates an associative array property by deeply merging or replacing keys.
     */
    public function mutateArrayProperty(string $name, callable $mutator): self
    {
        $property = $this->findProperty($name);

        if (!$property) {
            $this->upsertProperty($name, []);
            $property = $this->findProperty($name);
        }

        $defaultNode = $property->props[0]->default;

        $currentData = [];
        if ($defaultNode instanceof Node\Expr\Array_) {
            $currentData = $this->evaluateArrayNode($defaultNode);
        }

        // Pass existing array to user callback: returns modified array
        $modifiedData = $mutator($currentData);

        // Convert back to AST Array node
        $property->props[0]->default = $this->convertValueToNode($modifiedData);

        return $this;
    }

    /**
     * Injects code statements at the start or end of an existing method.
     */
    public function wrapMethod(string $methodName, ?string $prependCode = null, ?string $appendCode = null): self
    {
        $method = $this->findMethod($methodName);
        if (!$method) {
            throw new \InvalidArgumentException("Method '{$methodName}' not found.");
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
        
        /** @var ClassMethod $newMethod */
        $newMethod = $this->finder->findFirstInstanceOf($ast, ClassMethod::class);
        if (!$newMethod) {
            throw new \InvalidArgumentException("Invalid method code provided.");
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
        $this->targetClass->implements[] = new Node\Name\FullyQualified($interfaceName);
        return $this;
    }

    public function addTrait(string $traitName): self
    {
        $traitName = ltrim($traitName, '\\');
        $useStmt = new Node\Stmt\TraitUse([new Node\Name\FullyQualified($traitName)]);
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
            'private' => Class_::MODIFIER_PRIVATE,
            default => Class_::MODIFIER_PUBLIC,
        };
    }

    private function convertValueToNode(mixed $val): Node\Expr
    {
        if (is_array($val)) {
            $items = [];
            $isAssoc = array_keys($val) !== range(0, count($val) - 1);
            foreach ($val as $k => $v) {
                $items[] = new Node\Expr\ArrayItem(
                    $this->convertValueToNode($v),
                    $isAssoc ? new Node\Scalar\String_((string)$k) : null
                );
            }
            return new Node\Expr\Array_($items, ['kind' => Node\Expr\Array_::KIND_SHORT]);
        }
        if (is_string($val)) return new Node\Scalar\String_($val);
        if (is_int($val)) return new Node\Scalar\LNumber($val);
        if (is_float($val)) return new Node\Scalar\DNumber($val);
        if (is_bool($val)) return new Node\Expr\ConstFetch(new Node\Name($val ? 'true' : 'false'));
        if (is_null($val)) return new Node\Expr\ConstFetch(new Node\Name('null'));

        throw new \InvalidArgumentException("Unsupported value type for AST mapping.");
    }

    private function evaluateArrayNode(Node\Expr\Array_ $arrayNode): array
    {
        $printer = new StandardPrinter();
        $code = $printer->prettyPrint([$arrayNode]);
        return @eval("return {$code};") ?: [];
    }
}