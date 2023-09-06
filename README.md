[![codecov](https://codecov.io/gh/tinect/flysystem-garbage/graph/badge.svg?token=13DNLI45DD)](https://codecov.io/gh/tinect/flysystem-garbage)
[![Mutation Score Indicator](https://img.shields.io/badge/Mutation%20Score%20Indicator-100%25-green)](https://codecov.io/gh/tinect/flysystem-garbage)

This is a [Flysystem](https://github.com/thephpleague/flysystem) Adapter to move files into garbage folder when specific actions are taken

## Installation
```
composer require tinect/flysystem-garbage
```

## Usage example
```php
<?php
declare(strict_types=1);

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Tinect\Flysystem\Garbage\GarbageAdapter;

//Initialize your adapter
$adapter = new LocalFilesystemAdapter(
    '/my/path/'
);

//Put your adapter into the garbageAdapter
$adapter = new GarbageAdapter(
    $adapter
);

//Perform your actions as usual
$adapter->write('test.txt', 'content');
$adapter->delete('test.txt', 'content');

//see directory "garbage"
```