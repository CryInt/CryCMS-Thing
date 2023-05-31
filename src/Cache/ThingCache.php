<?php
/** @noinspection PhpUnused */

namespace CryCMS\Cache;

class ThingCache
{
    protected static $cache = [];

    public static function get($key)
    {
        return self::$cache[$key] ?? null;
    }

    public static function set($key, $value)
    {
        return self::$cache[$key] = $value;
    }

    public static function unset($key): void
    {
        if (array_key_exists($key, self::$cache)) {
            unset(self::$cache[$key]);
        }
    }

    public static function list(bool $onlyKeys = false): array
    {
        if ($onlyKeys) {
            return array_keys(self::$cache);
        }

        return self::$cache;
    }
}