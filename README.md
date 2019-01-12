# Hocklubs
A simple scraper of NL hockey clubs reports. The clubs are listed on [hockey.nl/clubs](https://hockey.nl/clubs/).

## Requirements
- Php version >=7.0.1

## Installation
This package is not available through Packagist. You can however clone it with Git `$ git clone https://github.com/mvune/hocklubs.git` or simply download the [ZIP](https://github.com/mvune/hocklubs/archive/master.zip), and then place it in your `vendor` directory.

## Usage
```php
<?php
require './vendor/autoload.php';

use Mvune\Hocklubs\HocklubService;

$hock = new HocklubService;

// Get an array of all clubs.
$clubs = $hock->getAll();

// Export directly to a given SQLite database file. If the file does not exist, it will be created.
$hock->exportToSqliteDb('example.db');
```

## Disclaimer
Regarding the scraped content, see: [hockey.nl/disclaimer](https://hockey.nl/disclaimer/).
