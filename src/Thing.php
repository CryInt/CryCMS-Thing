<?php
namespace CryCMS;

use Exception;

abstract class Thing
{
    protected static $_fields;

    protected $_attributes = [];
    protected $_attributes_default = [];

    protected $_metadata = [];

    protected $_action = 'insert';

    public function __construct(string $action = 'insert')
    {
        $this->_action = $action;
    }

    public function __set($field, $value): bool
    {
        try {
            if (self::issetField($field)) {
                $this->_attributes[$field] = $value;
                return true;
            }
        }
        catch (Exception $e) {
            print_r($e->getMessage());
            exit;
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

    public function setAttribute(string $key, $value): void
    {
        $this->$key = $value;
    }

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

    public function getAttribute($key)
    {
        return $this->__get($key);
    }

    public function getAttributes(): array
    {
        return array_merge($this->_attributes, $this->_metadata);
    }

    protected static function getFieldByKey($key): ?array
    {
        return static::$_fields[$key] ?? null;
    }

    /**
     * @throws Exception
     */
    public static function issetField($field): bool
    {
        $fields = self::getFields();
        return array_key_exists($field, $fields);
    }

    protected function relations(): array
    {
        return [];
    }

    private function getRelation($relation)
    {
        if (class_exists($relation['source']) === false) {
            return null;
        }

        if (is_callable(array($relation['source'], $relation['method']))) {
            $params = $this->extractParams($relation['params']);
            $obj = new $relation['source']();
            return call_user_func_array([$obj, $relation['method']], $params);
        }

        return null;
    }

    public function extractParams($params): array
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

    public function findByPk($pKId): ?Thing
    {
        $pK = self::getPk();
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

        $item = Db::table(self::getTable())->where($where)->values($values)->getOne();
        if (empty($item)) {
            return null;
        }

        $item = self::itemObject($item);
        $item->_action = 'update';

        return $item;
    }

    public function findByAttributes(array $attributes = []): ?Thing
    {
        $where = $values = [];

        foreach ($attributes as $attributeKey => $attributeValue) {
            $where[] = $attributeKey . " = :" . $attributeKey;
            $values[$attributeKey] = $attributeValue;
        }

        $item = Db::table(self::getTable())->where($where)->values($values)->getOne();

        if (empty($item)) {
            return null;
        }

        return self::itemObject($item);
    }

    public function findAllByAttributes(array $attributes = [], int $offset = null, int $limit = null): ?array
    {
        $where = $values = [];

        foreach ($attributes as $attributeKey => $attributeValue) {
            $where[] = $attributeKey . " = :" . $attributeKey;
            $values[$attributeKey] = $attributeValue;
        }

        $itemsDb = Db::table(self::getTable())->where($where)->values($values);

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

        return self::itemsObjects($items);
    }

    public function save()
    {
        $pK = self::getPk();
        if ($pK === null) {
            return false;
        }

        [$pK, $values] = self::getPkAndValues($pK, $this->_attributes);

        $class = self::class();

        if (method_exists($class, 'beforeSave')) {
            $values = $class->beforeSave($values);
        }

        $result = null;

        if ($this->_action === 'update') {
            $values = $this->removeUnchangedValues($values);
            if (count($values) > 0) {
                Db::table(self::getTable())->update($values, $pK);
            }

            $result = true;
        }

        if ($this->_action === 'insert') {
            Db::table(self::getTable())->insert($values);
            $result = $pK = Db::lastInsertId();
        }

        if (method_exists($class, 'afterSave')) {
            $item = $class->findByPk($pK);
            if ($item !== null) {
                $item->setAttributes($this->_metadata);
                $class->afterSave($item);
            }
        }

        return $result;
    }

    public function delete(): bool
    {
        try {
            $fields = self::getFields();
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

        $pK = self::getPk();
        if ($pK === null) {
            return false;
        }

        if ($this->_action === 'update') {
            [$pK,] = self::getPkAndValues($pK, $this->_attributes);

            $class = self::class();
            $item = $class->findByPk($pK);

            if ($item !== null) {
                Db::table(self::getTable())->delete($pK);

                if (method_exists($class, 'afterDelete')) {
                    $class->afterDelete($item);
                }
            }

            $result = $class->findByPk($pK);
            return $result === null;
        }

        return false;
    }

    public static function getPkAndValues($pK, $values): array
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
                $items[$key] = self::itemObject($item, $action);
            }
        }

        return $items;
    }

    public static function itemObject($item, string $action = 'update'): Thing
    {
        $class = self::class($action);
        $class->setAttributes($item, true);
        $class->itemExtension();

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
            $fields = self::getFields();
        }
        catch (Exception $e) {
            print_r($e->getMessage());
            return null;
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

    /**
     * @throws Exception
     */
    protected static function getFields(): array
    {
        if (static::$_fields !== null) {
            return static::$_fields;
        }

        if (empty(self::getTable())) {
            throw new Exception('No table specified in class: ' . static::class, 1);
        }

        $checkTableExists = Db::sql()->query("SHOW TABLES LIKE :table", ['table' => self::getTable()])->getOne();
        if (!empty($checkTableExists) && is_array($checkTableExists) && count($checkTableExists) === 1) {
            return static::$_fields = self::getFieldsByRaw(
                Db::table(self::getTable())->fields()
            );
        }

        throw new Exception("No table `" . self::getTable() . "` in database", 1);
    }

    private function removeUnchangedValues($values)
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
            $result[$field['COLUMN_NAME']] = self::unsetReturn($field, 'COLUMN_NAME');
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

    public static function class(string $action = 'insert'): Thing
    {
        $className = static::class;
        return new $className($action);
    }

    public static function getTable(): string
    {
        return static::$table;
    }
}