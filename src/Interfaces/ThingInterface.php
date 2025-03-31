<?php
namespace CryCMS\Interfaces;

use CryCMS\Db;

interface ThingInterface
{
    public const TABLE = null;
    public const SOFT_DELETE = false;

    public function byPk($primaryKey, bool $cached = false): ?ThingInterface;

    public function oneByAttributes(array $attributes = [], bool $cached = false): ?ThingInterface;

    public function oneByQuery(string $where, array $values = []): ?ThingInterface;

    public function listByAttributes(
        array $attributes = [],
        array $order = [],
        ?int  $offset = null,
        ?int $limit = null
    ): array;

    public function listByQuery(
        string $where,
        array  $values = [],
        array  $order = [],
        ?int   $offset = null,
        ?int $limit = null
    ): array;

    public function save(): bool;

    public function delete(): bool;

    public function setAttribute(string $key, $value): void;

    public function setAttributes(array $values, bool $withDefault = false): void;

    public function getAttribute($key);

    public function getAttributes(bool $withMetadata = true): array;

    public function getErrors(): array;

    public static function itemObject($item, string $action = 'update'): ?ThingInterface;

    public static function itemsObjects($items, string $action = 'update');

    public static function Db(): Db;
}