# Laravel Eloquent BATCH
[![Build Status](https://app.travis-ci.com/free3-5man/laravel-eloquent-batch.svg?branch=master)](https://app.travis-ci.com/free3-5man/laravel-eloquent-batch)

provide an util for batch Insert/update, also an eloquent collection macro batchSave for batch action more convenient in Laravel.

# Install
`composer require free3_5man/laravel-eloquent-batch`

# Service Provider
file app.php in array providers :

`Freeman\LaravelBatch\BatchServiceProvider::class,`

# Features
* make the insert data items assoc with keys, which has the same data structure with update data
* return insert ids when primary key is integer auto_increment
* MySQL, PostgreSQL, SQLite and SQL Server is supported
* json/jsonb field is supported
* auto chunked when generate batch sql
* support Eloqunent Collection macro batchSave for auto batchInsert and batchUpdate
* batchSave fill items with insert id when actually insert and primary key is integer auto_increment
* batchSave return updated models when actually update
* batchSave can save inserts and updates at the same time
* batchSave can control whether to touch updated_at with the param $touchUpdatedAt

# Batch Insert Example

```php
use App\Models\Player;
use Freeman\LaravelBatch\BatchUtil;

$data = [
    // this is the first item, columns of this item will be used as insert columns
    // if columns of any other items does not equal to this, false will be returned as result 
    // all the columns of each item required to be same
    [
        'name' => 'kaka',
        'birthday' => '1982-04-22',
    ],
    [
        'name' => 'nesta',
        'birthday' => '1976-03-19',
    ],
    [
        'name' => 'pirlo',
        'birthday' => '1979-05-19',
    ],
];
// batchInsert use the first item columns as insert columns default;
$ids = BatchUtil::ofModel(new Player)->batchInsert($data);
// if players primay key auto_increment, insert ids array ([1, 2, 3]) will be returned, else empty array ([]) will be returned

$data[1]['is_captain'] = true;
BatchUtil::ofModel(new Player)->batchInsert($data); // return false
```

# Batch Update Example

```php
$data = [
    [
        'id' => 1,
        'name' => 'kaka',
        'birthday' => '1982-04-22',
    ],
    [
        'id' => 2,
        'name' => 'nesta',
        'birthday' => '1976-03-19',
    ],
    [
        'id' => 3,
        'name' => 'pirlo',
        'birthday' => '1979-05-19',
    ],
];
BatchUtil::ofModel(new Player)->batchUpdate($data);
```

# batchSave Example

```php
$data = [
    [
        'name' => 'kaka',
        'birthday' => '1982-04-22',
    ],
    [
        'name' => 'nesta',
        'birthday' => '1976-03-19',
    ],
    [
        'name' => 'pirlo',
        'birthday' => '1979-05-19',
    ],
];
// batchSave will do batch insert here
$players = collect($data)->map(function($item) {
    return new Player($item);
})->batchSave();    // each player has id
dump($players->pluck('id')->toArray()); // [1, 2, 3]

// batchSave will do batch update here, updated_at will be touched automaticly
$players->map(function($player) {
    return $player->fill([
        'name' => $player->name . '-' . $player->id,
    ]);
})->batchSave();
dump($players->pluck('name')->toArray());   // ['kaka-1', 'nesta-2', 'pirlo-3']
```

# Testing

``` bash
$ composer test
```

