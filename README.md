# All is Object.

```php
<?php
use CryCMS\Thing;

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
    protected static $table = 'pages';
    protected static $_fields;

    protected function relations(): array
    {
        return [
            'image' => [
                // Класс откуда будут получены данные
                'source' => 'Image', 
                // Метод получения данных класса source
                'method' => 'findByPk', 
                // Параметры, которые будут переданы в метод
                'params' => ['image_id'], 
                // Будут ли получены данные сразу (true) или только поле запроса (false)
                'prompt' => true, 
            ]
        ];
    }
}
```

```php
<?php
use CryCMS\Thing;

class Image extends Thing
{
    protected static $table = 'images';
    protected static $_fields;
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
$page = Pages::findByPk(1);
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
$page = Pages::findByPk(1);
if ($page !== null) {
    $page->delete();
}
```

`Поиск по атрибутам`
```php
$pages = Pages::findAllByAttributes([
    'deleted' => 0,
], 0, 10);

$page = Pages::findByAttributes([
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

$pagesList = Db::table(Pages::getTable())->where(["deleted = '0'"])->getAll();
$pages = Pages::itemsObjects($pagesList);

$pageOne = Db::table(Pages::getTable())->where(["deleted = '0'"])->getOne();
$page = Pages::itemObject($pageOne);
```

`Relations - полуавтоматическое или автоматическое получение данных из других моделей`
```php
$page = Pages::findByPk(1);
print_r($image = $page->image);

Image Object
(
    [_attributes:protected] => Array
        (
            [image_id] => 1
            [url] => https://x03.ru/1.jpg
        )
)

Хотя поле и данных нет, но при указании Relation['image']
Данные были получены из Image::findByPk(image_id);
image_id автоматически подставляется из текущего обьекта
```