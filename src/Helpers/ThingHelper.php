<?php
namespace CryCMS\Helpers;

abstract class ThingHelper
{
    public static function getFieldsByRaw(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $result[$field['COLUMN_NAME']] = self::unsetReturn($field, 'COLUMN_NAME');
        }

        return $result;
    }

    public static function unsetReturn(array $array, string $unsetKey): array
    {
        if (array_key_exists($unsetKey, $array) !== false) {
            unset($array[$unsetKey]);
        }

        return $array;
    }

    public static function arrayValuesToLine($variable, string $separator = '_')
    {
        if (is_array($variable) === false) {
            return $variable;
        }

        $line = json_encode($variable);
        $line = preg_replace('/(\W)/', $separator, $line);
        $line = preg_replace('/_+/', '_', $line);
        return trim($line, '_ ');
    }

    public static function buildQuery($params): array
    {
        $queryWhere = $queryValues = [];

        if (count($params) > 0) {
            foreach ($params as $key => $value) {
                if (is_null($value)) {
                    $queryWhere[$key] = " `" . $key . "` IS NULL";
                }
                elseif (is_array($value)) {
                    $queryWhere[$key] = " `" . $key . "` IN (:" . $key . ")";
                    $queryValues[$key] = $value;
                }
                elseif (strpos($value, '!') === 0) {
                    $queryWhere[$key] = " `" . $key . "` != :" . $key;
                    $queryValues[$key] = substr($value, 1);
                }
                elseif (strpos($value, '>=') === 0) {
                    $queryWhere[$key] = " `" . $key . "` >= :" . $key;
                    $queryValues[$key] = substr($value, 2);
                }
                elseif (strpos($value, '>') === 0) {
                    $queryWhere[$key] = " `" . $key . "` > :" . $key;
                    $queryValues[$key] = substr($value, 1);
                }
                elseif (strpos($value, '<=') === 0) {
                    $queryWhere[$key] = " `" . $key . "` <= :" . $key;
                    $queryValues[$key] = substr($value, 2);
                }
                elseif (strpos($value, '<') === 0) {
                    $queryWhere[$key] = " `" . $key . "` < :" . $key;
                    $queryValues[$key] = substr($value, 1);
                }
                elseif (strpos($value, '%') !== false) {
                    $queryWhere[$key] = " `" . $key . "` LIKE :" . $key;
                    $queryValues[$key] = $value;
                }
                else {
                    $queryWhere[$key] = " `" . $key . "` = :" . $key;
                    $queryValues[$key] = $value;
                }
            }
        }

        $queryWhere = array_values($queryWhere);

        return [$queryWhere, $queryValues];
    }

    public static function getPrimaryKeyAndValues($primaryKey, $values, $action): array
    {
        if (!empty($primaryKey) && is_array($primaryKey) && count($primaryKey) > 0) {
            foreach ($primaryKey as $key => $null) {
                if (isset($values[$key])) {
                    $primaryKey[$key] = $values[$key];
                    if ($action !== 'insert') {
                        unset($values[$key]);
                    }
                }
            }
        }

        if (!empty($primaryKey) && is_string($primaryKey) && isset($values[$primaryKey])) {
            $tmp = $primaryKey;
            $primaryKey = [$primaryKey => $values[$primaryKey]];
            if ($action !== 'insert') {
                unset($values[$tmp]);
            }
        }

        return [$primaryKey, $values];
    }
}