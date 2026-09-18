<?php

namespace Jengo\Base\Facades;

use CodeIgniter\Model as CI4Model;
use RuntimeException;

/**
 * Class Model
 *
 * A static facade wrapper around CodeIgniter 4's base Model class, allowing for fluent and expressive query building via static calls.
 *
 * This class provides a clean, Laravel-like static interface to the underlying CI4 model methods,
 * making it easier to work with models without instantiating them manually. It also enhances `insert()` and `update()` 
 * by automatically capturing validation errors and storing them in the session flashdata, enabling easier error handling in views.
 *
 * ### Key Features:
 * - Static access to common query methods (`where()`, `find()`, `insert()`, etc.)
 * - Automatic instantiation via `__callStatic`
 * - Custom `insert()` and `update()` that store validation errors in session flashdata (`errors`)
 * - Useful for simplifying controller code and building expressive model interfaces
 *
 * ### Example:
 * ```php
 * $user = User::where('email', $email)->first();
 * User::insert(['name' => 'John']);
 * ```
 *
 * Note: Although this enables static usage, it still delegates execution to a live instance of the model behind the scenes.
 *
 * @package Jengo\Facades
 * 
 * @method static mixed find($id = null)
 * @method static mixed findAll(int $limit = 0, int $offset = 0)
 * @method static mixed first()
 * @method static mixed getCompiledSelect(bool $reset = true)
 * @method static static select(string $select = '*', bool $escape = null)
 * @method static static selectMax(string $select = '', string $alias = '')
 * @method static static selectMin(string $select = '', string $alias = '')
 * @method static static selectAvg(string $select = '', string $alias = '')
 * @method static static selectSum(string $select = '', string $alias = '')
 * @method static static join(string $table, string $cond, string $type = '', bool $escape = null)
 * @method static static where(string|array $key, mixed $value = null, bool $escape = null)
 * @method static static orWhere(string|array $key, mixed $value = null, bool $escape = null)
 * @method static static whereIn(string $key = null, array $values = null, bool $escape = null)
 * @method static static orWhereIn(string $key = null, array $values = null, bool $escape = null)
 * @method static static whereNotIn(string $key = null, array $values = null, bool $escape = null)
 * @method static static orWhereNotIn(string $key = null, array $values = null, bool $escape = null)
 * @method static static like(string|array $field, string $match = '', string $side = 'both', bool $escape = null)
 * @method static static orLike(string|array $field, string $match = '', string $side = 'both', bool $escape = null)
 * @method static static notLike(string|array $field, string $match = '', string $side = 'both', bool $escape = null)
 * @method static static orNotLike(string|array $field, string $match = '', string $side = 'both', bool $escape = null)
 * @method static static groupBy(string|string[] $by, bool $escape = null)
 * @method static static having(string|array $key, string $value = null, bool $escape = null)
 * @method static static orderBy(string $orderBy, string $direction = '', bool $escape = null)
 * @method static static set(mixed $key, string $value = '', bool $escape = null)
 * @method static static limit(int $value, int $offset = 0)
 * @method static static offset(int $offset)
 * @method static static countAllResults(bool $reset = true)
 * @method static static countAll()
 * @method static mixed insert(array|object|null $data = null, bool $returnID = true)
 * @method static bool insertBatch(array $set = null, bool $escape = null, int $batchSize = 100)
 * @method static bool update($id = null, $data = null)
 * @method static bool updateBatch(array $set = null, string $index = null, int $batchSize = 100)
 * @method static bool delete($id = null, bool $purge = false)
 * @method static array errors()
 * @method static string getLastQuery()
 * @method static bool save(array|object|null $data)
 * @method static array asArray()
 * @method static object asObject(string $className = 'stdClass')
 * @method static mixed with(string ...$associations)
 */

class ModelFacade
{
    protected ?CI4Model $modelInstance = null;
    protected ?string $modelClass = null;
    protected static ?string $staticClass = null;

    public function __construct(string|CI4Model $model)
    {
        if (is_string($model)) {
            $this->modelClass = $model;
        } else {
            $this->modelInstance = $model;
            $this->modelClass = get_class($model);
        }

        static::$staticClass = $this->modelClass;
    }

    protected function getModel(): CI4Model
    {
        if ($this->modelInstance !== null) {
            return $this->modelInstance;
        }

        if (!class_exists($this->modelClass ?? '')) {
            throw new RuntimeException("Class [{$this->modelClass}] is undefined");
        }

        $instance = new ($this->modelClass)();

        if (!($instance instanceof CI4Model)) {
            throw new RuntimeException("Class must extend CI4's Model class");
        }

        return $this->modelInstance = $instance;
    }

    protected static function instance(): CI4Model
    {
        if (!class_exists(static::$staticClass ?? '')) {
            throw new RuntimeException("Class is undefined");
        }

        $instance = new (static::$staticClass)();

        if (!($instance instanceof CI4Model)) {
            throw new RuntimeException("Class must extend CI4's Model class");
        }

        return $instance;
    }

    /**
     * Find a record by its primary key or throw a PageNotFoundException.
     */
    public function findOrFail(int|string $id): mixed
    {
        $result = $this->getModel()->find($id);

        if ($result === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(
                sprintf('Record [%s] not found for model [%s].', (string) $id, $this->modelClass ?? 'Model')
            );
        }

        return $result;
    }

    /**
     * Execute the query and get the first result or throw a PageNotFoundException.
     */
    public function firstOrFail(): mixed
    {
        $result = $this->getModel()->first();

        if ($result === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(
                sprintf('No records found for model [%s].', $this->modelClass ?? 'Model')
            );
        }

        return $result;
    }

    /**
     * Apply the callback if the given value is truthy.
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        $val = value($value);

        if ($val) {
            $callback($this, $val);
        } elseif ($default !== null) {
            $default($this, $val);
        }

        return $this;
    }

    /**
     * Apply the callback if the given value is falsy.
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        $val = value($value);

        if (!$val) {
            $callback($this, $val);
        } elseif ($default !== null) {
            $default($this, $val);
        }

        return $this;
    }

    /**
     * Pass the facade to the given callback and return the facade.
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Pass the facade to the given callback and return the result.
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    public function __call(string $method, array $args): mixed
    {
        $result = $this->getModel()->$method(...$args);

        if ($result === $this->getModel()) {
            return $this;
        }

        return $result;
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        $facade = new static(static::$staticClass ?? '');

        return $facade->__call($method, $args);
    }
}