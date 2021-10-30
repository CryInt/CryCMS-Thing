<?php
namespace CryCMS;

use Exception;

abstract class Thing
{
    protected static $_fields;
    protected static $_fields_raw;

    protected $_attributes;
    protected $_attributes_default = [];

    protected $_metadata = [];

    public function __construct()
    {
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

        //$relations = $this->relations();
        //if (isset($relations[$field])) {
        //    return $this->_metadata[$field] = $this->getRelation($relations[$field]);
        //}

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

        //$relations = $this->relations();
        //if (isset($relations[$field])) {
        //    return ($this->_metadata[$field] = $this->getRelation($relations[$field])) !== null;
        //}

        return false;
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

    public function getAttributes(): array
    {
        return array_merge($this->_attributes, $this->_metadata);
    }

    /**
     * @throws Exception
     */
    public static function issetField($field): bool
    {
        $fields = self::getFields();
        return array_key_exists($field, $fields);
    }

    /**
     * @throws Exception
     */
    protected static function getFields(): array
    {
        if (static::$_fields !== null) {
            return static::$_fields;
        }

        if (empty(static::$table)) {
            throw new Exception('No table specified in class: ' . static::class, 1);
        }

        $checkTableExists = Db::sql()->query("SHOW TABLES LIKE :table", ['table' => static::$table])->getOne();
        if (!empty($checkTableExists) && is_array($checkTableExists) && count($checkTableExists) === 1) {
            static::$_fields_raw = Db::table(static::$table)->fields();
            return static::$_fields = self::getFieldsByRaw(static::$_fields_raw);
        }

        throw new Exception("No table `" . static::$table . "` in database", 1);
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
}