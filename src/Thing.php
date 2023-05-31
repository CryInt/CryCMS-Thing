<?php
/** @noinspection PhpUnused */
/** @noinspection PhpUnhandledExceptionInspection */

namespace CryCMS;

use CryCMS\Cache\ThingCache;
use CryCMS\Exceptions\ThingException;
use CryCMS\Exceptions\ThingValidateException;
use CryCMS\Interfaces\ThingInterface;
use CryCMS\Helpers\ThingHelper;

abstract class Thing implements ThingInterface
{
    protected $_action;

    protected $_metadata = [];

    protected $_attributes = [];
    protected $_attributes_default = [];

    protected $_errors = [];

    public function __construct(string $action = 'insert')
    {
        $this->_action = $action;
    }

    public static function find(): ThingInterface
    {
        return new static();
    }

    public function byPk($primaryKey, bool $cached = false): ?ThingInterface
    {
        if ($cached) {
            $cacheKey = static::class . '_byPk_' . ThingHelper::arrayValuesToLine($primaryKey);
            $cache = ThingCache::get($cacheKey);
            if ($cache !== null) {
                return $cache;
            }
        }

        $primaryKeyField = $this->getPrimaryKey();
        if ($primaryKeyField === null) {
            return null;
        }

        $where = $values = [];

        if (is_array($primaryKeyField)) {
            if (!is_array($primaryKey)) {
                return null;
            }

            foreach ($primaryKeyField as $key => $null) {
                if (array_key_exists($key, $primaryKey)) {
                    $where[$key] = " `" . $key . "` = :" . $key;
                    $values[$key] = $primaryKey[$key];
                }
            }
        }
        elseif (is_string($primaryKeyField)) {
            if (is_array($primaryKey) && count($primaryKey) > 1) {
                return null;
            }

            if (is_array($primaryKey) && count($primaryKey) === 1) {
                $primaryKey = array_shift($primaryKey);
            }

            $where[$primaryKeyField] = " `" . $primaryKeyField . "` = :" . $primaryKeyField;
            $values[$primaryKeyField] = $primaryKey;
        }

        if (count($where) === 0 || count($values) === 0) {
            return null;
        }

        $item = self::Db()
            ->where($where)
            ->values($values)
            ->getOne();

        if (empty($item)) {
            return null;
        }

        $itemAsObject = self::itemObject($item);
        if ($cached) {
            ThingCache::set($cacheKey, $itemAsObject);
        }

        return $itemAsObject;
    }

    public function oneByAttributes(array $attributes = [], bool $cached = false): ?ThingInterface
    {
        if ($cached) {
            $cacheKey = static::class . '_oneByAttributes_' . ThingHelper::arrayValuesToLine($attributes);
            $cache = ThingCache::get($cacheKey);
            if ($cache !== null) {
                return $cache;
            }
        }

        [$where, $values] = ThingHelper::buildQuery($attributes);

        $item = self::Db()
            ->where($where)
            ->values($values)
            ->getOne();

        if (empty($item)) {
            return null;
        }

        $itemAsObject = self::itemObject($item);
        if ($cached) {
            ThingCache::set($cacheKey, $itemAsObject);
        }

        return $itemAsObject;
    }

    public function oneByQuery(string $where, array $values = []): ?ThingInterface
    {
        $item = self::Db()
            ->where([$where])
            ->values($values)
            ->getOne();

        if (empty($item)) {
            return null;
        }

        return self::itemObject($item);
    }

    public function listByAttributes(
        array $attributes = [],
        array $order = [],
        int $offset = null,
        int $limit = null
    ): array
    {
        [$where, $values] = ThingHelper::buildQuery($attributes);

        $itemsDb = self::Db()
            ->calcRows()
            ->where($where)
            ->values($values);

        return $this->listBySomething($itemsDb, $order, $offset, $limit);
    }

    public function listByQuery(
        string $where,
        array $values = [],
        array $order = [],
        int $offset = null,
        int $limit = null
    ): array
    {
        $itemsDb = self::Db()
            ->calcRows()
            ->where([$where])
            ->values($values);

        return $this->listBySomething($itemsDb, $order, $offset, $limit);
    }

    protected function listBySomething(
        Db $itemsDb,
        array $order = [],
        int $offset = null,
        int $limit = null
    ): array
    {
        if (!empty($order)) {
            $itemsDb->orderBy($order);
        }

        if ($limit !== null) {
            $itemsDb->limit($limit);
        }

        if ($offset !== null) {
            $itemsDb->offset($offset);
        }

        $items = $itemsDb->getAll();

        return [
            'list' => self::itemsObjects($items),
            'count' => self::Db()::getFoundRows(),
        ];
    }

    public function save(): bool
    {
        $primaryKeyField = $this->getPrimaryKey();
        if ($primaryKeyField === null) {
            return false;
        }

        if (method_exists($this, 'validate')) {
            $this->cleanErrors();
            $this->validate();

            if (!empty($this->getErrors())) {
                throw new ThingValidateException('Validation error');
            }
        }

        if (method_exists($this, 'beforeSave')) {
            $this->beforeSave();
        }

        [$primaryKeyCondition, $values] = ThingHelper::getPrimaryKeyAndValues(
            $primaryKeyField,
            $this->_attributes,
            $this->_action
        );

        $result = null;

        if ($this->_action === 'update') {
            $values = $this->removeUnchangedValues($values);
            if (count($values) > 0) {
                self::Db()->update($values, $primaryKeyCondition);
                return true;
            }

            return false;
        }

        if ($this->_action === 'insert') {
            self::Db()->insert($values);

            $insertId = self::Db()::lastInsertId();
            if (!empty($insertId)) {
                $primaryKeyCondition = $insertId;
            }

            $result = $this::find()->byPk($primaryKeyCondition);
            if ($result !== null) {
                $this->_action = 'update';
                $this->setAttributes($result->getAttributes(false), true);
            }
        }

        if ($result !== null && method_exists($this, 'afterSave')) {
            $result->afterSave();
        }

        return $result !== null;
    }

    public function delete(): bool
    {
        if (method_exists($this, 'beforeDelete')) {
            $this->beforeDelete();
        }

        if (static::SOFT_DELETE) {
            $deleted = $this->getAttribute('deleted');
            if ($deleted === 0 || $deleted === '0') {
                $this->setAttribute('deleted', 1);
                $result = $this->save();
                if ($result && method_exists($this, 'afterDelete')) {
                    $this->afterDelete();
                    return true;
                }
            }

            return false;
        }

        $primaryKeyField = $this->getPrimaryKey();
        if ($primaryKeyField === null) {
            return false;
        }

        if ($this->_action === 'update') {
            [$primaryKeyCondition,] = ThingHelper::getPrimaryKeyAndValues(
                $primaryKeyField,
                $this->_attributes,
                $this->_action
            );

            self::Db()->delete($primaryKeyCondition);

            if (method_exists($this, 'afterDelete')) {
                $this->afterDelete();
            }

            $result = $this::find()->byPk($primaryKeyCondition);
            return $result === null;
        }

        return false;
    }

    public static function itemObject($item, string $action = 'update'): ?ThingInterface
    {
        if (!is_array($item)) {
            return null;
        }

        $class = new static($action);
        $class->setAttributes($item, true);
        $class->itemExtension();

        return $class;
    }

    public static function itemsObjects($items, string $action = 'update')
    {
        if (!empty($items) && count($items) > 0) {
            foreach ($items as $key => $item) {
                $items[$key] = self::itemObject($item, $action);
            }
        }

        return $items;
    }

    /**
     * @throws ThingValidateException
     */
    protected function validate(): void
    {
        if ($this === null) {
            throw new ThingValidateException('Empty object');
        }
    }

    protected function beforeSave(): void
    {

    }

    protected function afterSave(): void
    {

    }

    protected function beforeDelete(): void
    {

    }

    protected function afterDelete(): void
    {

    }

    protected function itemExtension(): void
    {

    }

    public function setAttribute(string $key, $value): void
    {
        $this->$key = $value;
    }

    public function setAttributes(array $values, bool $withDefault = false): void
    {
        if (!empty($values) && count($values) > 0) {
            foreach ($values as $key => $value) {
                $this->setAttribute($key, $value);

                if ($withDefault === true) {
                    $this->_attributes_default[$key] = $value;
                }
            }
        }
    }

    public function getAttribute($key)
    {
        return $this->__get($key);
    }

    public function getAttributes(bool $withMetadata = true): array
    {
        if ($withMetadata) {
            return array_merge($this->_attributes, $this->_metadata);
        }

        return $this->_attributes;
    }

    public static function Db(): Db
    {
        return Db::table(self::getTable());
    }

    public function __set($field, $value): bool
    {
        if (static::issetField($field)) {
            $this->_attributes[$field] = $value;
            return true;
        }

        $this->_metadata[$field] = $value;
        return true;
    }

    public function __get($field)
    {
        return $this->_attributes[$field] ?? $this->_metadata[$field] ?? null;
    }

    public function __isset($field): bool
    {
        if (isset($this->_attributes[$field])) {
            return true;
        }

        if (isset($this->_metadata[$field])) {
            return true;
        }

        return false;
    }

    public function getError(string $field): ?string
    {
        return $this->_errors[$field] ?? null;
    }
    
    public function getErrors(): array
    {
        return $this->_errors;
    }

    protected function addError(string $field, string $error): void
    {
        $this->_errors[$field] = $error;
    }

    protected function cleanErrors(): void
    {
        $this->_errors = [];
    }

    protected function getPrimaryKey()
    {
        $primaryKey = [];

        $fields = self::getFields();
        if (!empty($fields) && count($fields) > 0) {
            foreach ($fields as $field => $data) {
                if (empty($data['COLUMN_KEY']) || $data['COLUMN_KEY'] !== 'PRI') {
                    continue;
                }

                $primaryKey[$field] = $field;
            }
        }

        if (count($primaryKey) === 1) {
            return key($primaryKey);
        }

        if (count($primaryKey) >= 1) {
            return $primaryKey;
        }

        return null;
    }

    protected static function issetField($field): bool
    {
        $fields = self::getFields();
        return array_key_exists($field, $fields);
    }

    protected static function getFields(): array
    {
        $table = self::getTable();

        $cacheKey = __METHOD__ . '_' . $table;

        $cache = ThingCache::get($cacheKey);
        if ($cache !== null) {
            return $cache;
        }

        $checkTableExists = Db::sql()
            ->query("SHOW TABLES LIKE :table", ['table' => $table])
            ->getOne();

        if (
            !empty($checkTableExists) &&
            is_array($checkTableExists) &&
            count($checkTableExists) === 1
        ) {
            return ThingCache::set(
                $cacheKey,
                ThingHelper::getFieldsByRaw(
                    Db::table($table)->fields()
                )
            );
        }

        throw new ThingException('No table `' . $table . '` in database');
    }

    protected function removeUnchangedValues($values)
    {
        if (!empty($this->_attributes_default) && count($this->_attributes_default) > 0) {
            foreach ($this->_attributes_default as $defaultKey => $defaultValue) {
                if (array_key_exists($defaultKey, $values)) {
                    $checkValue = $values[$defaultKey];
                    if (
                        is_array($checkValue) === false &&
                        is_null($checkValue) === false &&
                        is_bool($checkValue) === false
                    ) {
                        $checkValue = (string)$checkValue;
                    }

                    if ($checkValue === $defaultValue) {
                        unset($values[$defaultKey]);
                    }
                }
            }
        }

        return $values;
    }

    /**
     * @throws ThingException
     */
    protected static function getTable(): string
    {
        if (static::TABLE === null) {
            throw new ThingException('Table not specified in class: ' . static::class);
        }

        return static::TABLE;
    }
}