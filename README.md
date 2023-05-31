# All is Object.

```php
<?php
use CryCMS\Helpers\Thing;

/**
 * @property string $title
 * @property string $url
 * @property string $text
 * @property array $image_id
 * @property int $deleted
 *
 * @property array $image
 */

class Pages extends Thing
{
    public const TABLE = 'pages';
    public const SOFT_DELETE = true;
}
```

```php
<?php
use CryCMS\Helpers\Thing;

class Image extends Thing
{
    public const TABLE = 'images';
    
    protected function validate(): void
    {
        if (empty($this->title)) {
            $this->addError('title', 'Заголовок не может быть пустым');
        }
    }
}
```

`Создание новой записи`
```php
$page = new Pages();
$page->title = 'TEST';
$page->setAttributes([
    'url' => '/test/',
    'text' => 'Тест',
]);

$result = $page->save();
```

`Изменение записи`
```php
$page = Pages::find()->byPk(1);
if ($page !== null) {
    $page->image_id = 1;
    $page->setAttributes([
        'text' => 'Новый текст',
    ]);
    $page->save();
}
```

`Удаление записи`
```php
$page = Pages::find()->byPk(1);
if ($page !== null) {
    $page->delete();
}
```

`Поиск по атрибутам`
```php
$pages = Pages::find()->listByAttributes([
    'deleted' => 0,
], 0, 10);

$page = Pages::find()->oneByAttributes([
    'deleted' => 0,
]);
```

`Получение обьектов из SQL выборки` - [CryCMS-Db](https://github.com/CryInt/CryCMS-Db)
```php
$pages = Pages::Db()->where(["deleted = '0'"])->getAll();
$pages = Pages::itemsObjects($pages);

$page = Pages::Db()->where(["deleted = '0'"])->getOne();
$page = Pages::itemObject($page);

OR

$pagesList = Db::table(Pages::TABLE)->where(["deleted = '0'"])->getAll();
$pages = Pages::itemsObjects($pagesList);

$pageOne = Db::table(Pages::TABLE)->where(["deleted = '0'"])->getOne();
$page = Pages::itemObject($pageOne);
```