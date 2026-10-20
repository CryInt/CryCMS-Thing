<?php
namespace CryCMS\Helpers;

use CryCMS\Helpers;

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
                if (is_array($value)) {
                    if (empty($value)) {
                        continue;
                    }

                    if (self::valuesWithPlaceholders($value)) {
                        foreach ($value as $n => $v) {
                            $newKey = $key . '_' . $n;
                            [$where, $value] = self::makeWhereValue($key, $v, $newKey);

                            $queryWhere[$newKey] = $where;
                            if ($value !== null) {
                                $queryValues[$newKey] = $value;
                            }
                        }

                        continue;
                    }
                }

                [$where, $value] = self::makeWhereValue($key, $value);

                $queryWhere[$key] = $where;
                if ($value !== null) {
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

    protected static function makeWhereValue(string $key, mixed $value, ?string $valueKey = null): array
    {
        $returnWhere = null;
        $returnValue = null;

        if ($valueKey === null) {
            $valueKey = $key;
        }

        if (is_null($value)) {
            $returnWhere = " `" . $key . "` IS NULL";
        }
        elseif (is_array($value)) {
            $returnWhere = " `" . $key . "` IN (:" . $valueKey . ")";
            $returnValue = $value;
        }
        elseif (strpos($value, '!') === 0) {
            $returnWhere = " `" . $key . "` != :" . $valueKey;
            $returnValue = substr($value, 1);
        }
        elseif (strpos($value, '>=') === 0) {
            $returnWhere = " `" . $key . "` >= :" . $valueKey;
            $returnValue = substr($value, 2);
        }
        elseif (strpos($value, '>') === 0) {
            $returnWhere = " `" . $key . "` > :" . $valueKey;
            $returnValue = substr($value, 1);
        }
        elseif (strpos($value, '<=') === 0) {
            $returnWhere = " `" . $key . "` <= :" . $valueKey;
            $returnValue = substr($value, 2);
        }
        elseif (strpos($value, '<') === 0) {
            $returnWhere = " `" . $key . "` < :" . $valueKey;
            $returnValue = substr($value, 1);
        }
        elseif (strpos($value, '%') !== false) {
            $returnWhere = " `" . $key . "` LIKE :" . $valueKey;
            $returnValue = $value;
        }
        else {
            $returnWhere = " `" . $key . "` = :" . $valueKey;
            $returnValue = $value;
        }

        return [$returnWhere, $returnValue];
    }

    protected static function valuesWithPlaceholders(array $values): bool
    {
        foreach ($values as $key => $value) {
            $char = substr($value, 0, 1);
            if (in_array($char, ['!', '<', '>', '%'])) {
                return true;
            }
        }

        return false;
    }
}