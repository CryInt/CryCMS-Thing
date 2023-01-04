<?php
namespace CryCMS;

use Exception;
use RuntimeException;

abstract class Thing
{
    protected static $_fields;

    protected $_attributes = [];
    protected $_attributes_default = [];

    protected $_metadata = [];

    protected $_action = 'insert';

    public $_errors;

    public function __construct(string $action = 'insert')
    {
        $this->_action = $action;
    }

    public function __set($field, $value): bool
    {
        try {
            if (static::issetField($field)) {
                $this->_attributes[$field] = $value;
                return true;
            }
        }
        catch (Exception $e) {
            die($e->getMessage());
        }

        $this->_metadata[$field] = $value;
        return true;
    }

    public function __get($field)
    {
        if (isset($this->_attributes[$field])) {
            return $this->_attributes[$field];
        }

        if (isset($this->_metadata[$field])) {
            return $this->_metadata[$field];
        }

        $relations = $this->relations();
        if (isset($relations[$field])) {
            return $this->_metadata[$field] = $this->getRelation($relations[$field]);
        }

        return null;
    }

    public function __isset($field): bool
    {
        if (isset($this->_attributes[$field])) {
            return true;
        }

        if (isset($this->_metadata[$field])) {
            return true;
        }

        $relations = $this->relations();
        if (isset($relations[$field])) {
            return ($this->_metadata[$field] = $this->getRelation($relations[$field])) !== null;
        }

        return false;
    }

    /**
     * @noinspection PhpUnused
     */
    public function setAttribute(string $key, $value): void
    {
        $this->$key = $value;
    }

    /**
     * @noinspection PhpUnused
     */
    public function setAttributes(array $values, bool $withDefault = false): void
    {
        if (!empty($values) && count($values) > 0) {
            foreach ($values as $key => $value) {
                $this->$key = $value;

                if ($withDefault === true) {
                    $this->_attributes_default[$key] = $value;
                }
            }
        }
    }

    /**
     * @noinspection PhpUnused
     */
    public function getAttribute($key)
    {
        return $this->__get($key);
    }

    /**
     * @noinspection PhpUnused
     */
    public function getAttributes(): array
    {
        return array_merge($this->_attributes, $this->_metadata);
    }

    /**
     * @noinspection PhpUnused
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    protected static function issetField($field): bool
    {
        $fields = static::getFields();
        return array_key_exists($field, $fields);
    }

    protected function relations(): array
    {
        return [];
    }

    protected function getRelation($relation)
    {
        if (class_exists($relation['source']) === false) {
            return null;
        }

        if (is_callable(array($relation['source'], $relation['method']))) {
            $params = $this->extractParams($relation['params']);
            return call_user_func_array([$relation['source'], $relation['method']], $params);
        }

        return null;
    }

    protected function extractParams($params): array
    {
        $result = [];

        foreach ($params as $param) {
            if (isset($this->$param)) {
                $result[$param] = $this->$param;
            } else {
                $result[$param] = null;
            }
        }

        return $result;
    }

    /**
     * @noinspection PhpUnused
     */
    public static function findByPk($pKId)
    {
        $pK = static::getPk();
        if ($pK === null) {
            return null;
        }

        $where = $values = [];

        if (is_array($pK)) {
            if (!is_array($pKId)) {
                return null;
            }

            foreach ($pK as $key => $null) {
                if (isset($pKId[$key])) {
                    $where[$key] = " `" . $key . "` = :" . $key;
                    $values[$key] = $pKId[$key];
                }
            }
        } elseif (is_string($pK)) {
            if (is_array($pKId) && count($pKId) > 1) {
                return null;
            }

            if (is_array($pKId) && count($pKId) === 1) {
                $pKId = array_shift($pKId);
            }

            $where[$pK] = " `" . $pK . "` = :" . $pK;
            $values[$pK] = $pKId;
        }

        if (count($where) === 0 || count($values) === 0) {
            return null;
        }

        $item = Db::table(static::getTable())->where($where)->values($values)->getOne();
        if (empty($item)) {
            return null;
        }

        $item = static::itemObject($item);
        $item->_action = 'update';

        return $item;
    }

    /**
     * @noinspection PhpUnused
     */
    public static function findByAttributes(array $attributes = [])
    {
        $where = $values = [];

        foreach ($attributes as $attributeKey => $attributeValue) {
            $where[] = $attributeKey . " = :" . $attributeKey;
            $values[$attributeKey] = $attributeValue;
        }

        $item = Db::table(static::getTable())->where($where)->values($values)->getOne();

        if (empty($item)) {
            return null;
        }

        return static::itemObject($item);
    }

    /**
     * @noinspection PhpUnused
     */
    public static function findAllByAttributes(array $attributes = [], int $offset = null, int $limit = null): ?array
    {
        $where = $values = [];

        foreach ($attributes as $attributeKey => $attributeValue) {
            $where[] = $attributeKey . " = :" . $attributeKey;
            $values[$attributeKey] = $attributeValue;
        }

        $itemsDb = Db::table(static::getTable())->where($where)->values($values);

        if ($limit !== null) {
            $itemsDb->limit($limit);
        }

        if ($offset !== null) {
            $itemsDb->offset($offset);
        }

        $items = $itemsDb->getAll();

        if (empty($items)) {
            return null;
        }

        return static::itemsObjects($items);
    }

    public static function Db(): Db
    {
        return Db::table(static::getTable());
    }

    public function save()
    {
        $pK = static::getPk();
        if ($pK === null) {
            return false;
        }

        [$pK, $values] = static::getPkAndValues($pK, $this->_attributes);

        if (method_exists($this, 'beforeSave')) {
            $values = $this->beforeSave($values);
        }

        $result = null;

        if ($this->_action === 'update') {
            $values = $this->removeUnchangedValues($values);
            if (count($values) > 0) {
                Db::table(static::getTable())->update($values, $pK);
            }

            $result = true;
        }

        if ($this->_action === 'insert') {
            Db::table(static::getTable())->insert($values);
            $result = $pK = Db::lastInsertId();
        }

        if (method_exists($this, 'afterSave')) {
            $item = $this::findByPk($pK);
            if ($item !== null) {
                $item->setAttributes($this->_metadata);
                $this->afterSave($item);
            }
        }

        return $result;
    }

    /**
     * @noinspection PhpUnused
     */
    public function delete(): bool
    {
        try {
            $fields = static::getFields();
            if (isset($fields['deleted'])) {
                $deleted = $this->getAttribute('deleted');
                if ($deleted === 0 || $deleted === '0') {
                    $this->setAttribute('deleted', 1);
                    return $this->save();
                }

                return true;
            }
        }
        catch (Exception $e) {}

        $pK = static::getPk();
        if ($pK === null) {
            return false;
        }

        if ($this->_action === 'update') {
            [$pK,] = static::getPkAndValues($pK, $this->_attributes);

            $class = static::class();
            $item = $class::findByPk($pK);

            if ($item !== null) {
                Db::table(static::getTable())->delete($pK);

                if (method_exists($class, 'afterDelete')) {
                    $class->afterDelete($item);
                }
            }

            $result = $class::findByPk($pK);
            return $result === null;
        }

        return false;
    }

    protected static function getPkAndValues($pK, $values): array
    {
        if (!empty($pK) && is_array($pK) && count($pK) > 0) {
            foreach ($pK as $key => $null) {
                if (isset($values[$key])) {
                    $pK[$key] = $values[$key];
                    unset($values[$key]);
                }
            }
        }

        if (!empty($pK) && is_string($pK) && isset($values[$pK])) {
            $tmp = $pK;
            $pK = [$pK => $values[$pK]];
            unset($values[$tmp]);
        }

        return [$pK, $values];
    }

    public static function itemsObjects($items, string $action = 'update')
    {
        if (!empty($items) && count($items) > 0) {
            foreach ($items as $key => $item) {
                $items[$key] = static::itemObject($item, $action);
            }
        }

        return $items;
    }

    public static function itemObject($item, string $action = 'update')
    {
        if (!is_array($item)) {
            return null;
        }

        $class = static::class($action);
        $class->setAttributes($item, true);
        $class->itemExtension();

        $relations = $class->relations();
        if (!empty($relations)) {
            foreach ($relations as $field => $relation) {
                if (!empty($relation['prompt']) && $relation['prompt'] === true) {
                    $class->_metadata[$field] = $class->getRelation($relation);
                }
            }
        }

        return $class;
    }

    protected function itemExtension(): void
    {
    }

    protected function beforeSave($values)
    {
        return $values;
    }

    protected function afterSave($item): void
    {
    }

    protected function afterDelete($item): void
    {
    }

    protected static function getPk()
    {
        $pK = [];

        try {
            $fields = static::getFields();
        }
        catch (Exception $e) {
            die($e->getMessage());
        }

        if (!empty($fields) && count($fields) > 0) {
            foreach ($fields as $field => $data) {
                if (empty($data['COLUMN_KEY']) || $data['COLUMN_KEY'] !== 'PRI') {
                    continue;
                }

                $pK[$field] = $field;
            }
        }

        if (count($pK) === 1) {
            return key($pK);
        }

        if (count($pK) >= 1) {
            return $pK;
        }

        return null;
    }

    protected static function getFields(): array
    {
        if (static::$_fields !== null) {
            return static::$_fields;
        }

        if (empty(static::getTable())) {
            throw new RuntimeException('No table specified in class: ' . static::class, 1);
        }

        $checkTableExists = Db::sql()->query("SHOW TABLES LIKE :table", ['table' => static::getTable()])->getOne();
        if (!empty($checkTableExists) && is_array($checkTableExists) && count($checkTableExists) === 1) {
            return static::$_fields = static::getFieldsByRaw(
                Db::table(static::getTable())->fields()
            );
        }

        throw new RuntimeException("No table `" . static::getTable() . "` in database", 1);
    }

    protected function removeUnchangedValues($values)
    {
        if (!empty($this->_attributes_default) && count($this->_attributes_default) > 0) {
            foreach ($this->_attributes_default as $defaultKey => $defaultValue) {
                if (array_key_exists($defaultKey, $values) && $values[$defaultKey] === $defaultValue) {
                    unset($values[$defaultKey]);
                }
            }
        }

        return $values;
    }

    protected static function getFieldsByRaw(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $result[$field['COLUMN_NAME']] = static::unsetReturn($field, 'COLUMN_NAME');
        }

        return $result;
    }

    protected static function unsetReturn(array $array, string $unsetKey): array
    {
        if (array_key_exists($unsetKey, $array) !== false) {
            unset($array[$unsetKey]);
        }

        return $array;
    }

    public static function class(string $action = 'insert')
    {
        $className = static::class;
        return new $className($action);
    }

    public static function getTable(): string
    {
        return static::$table;
    }
}