wicket-sdk-php
==============

[![Build Status](https://travis-ci.org/industrialdev/wicket-sdk-php.svg?branch=master)](https://travis-ci.org/industrialdev/wicket-sdk-php)

A PHP library to interact with the [Wicket API](https://wicketapi.docs.apiary.io)

## Wicket

> Wicket is the world's first Member Data Platform.

To learn more please visit the [Wicket Website](https://wicket.io)

## Installing Wicket SDK

The recommended way to install the WicketSDK is through
[Composer](http://getcomposer.org).


Once Composer is installed, create a `composer.json` file to include the latest development version of WicketSDK:

```json
{
  "repositories": [
    {
      "type": "git",
      "url": "https://github.com/industrialdev/wicket-sdk-php.git"
    }
  ],
  "require": {
    "industrialdev/wicket-sdk-php": "dev-master"
  }
}
```

Next, install the packages:

```bash
composer install
```

After installing, you need only to `require_once` Composer's autoloader in your code:

```php
require_once 'vendor/autoload.php';
```

You can then later update Wicket using composer:

```bash
composer update
```

## Using the SDK

```php
<?php
require_once "vendor/autoload.php";

$client = new Wicket\Client(
	env('API_APP_KEY'),
	env('API_JWT_SECRET'),
	'https://<tenant>-api.wicketcloud.com'
);
$client->authorize(env('PERSON_ID'));

$orgs = $client->organizations->all();    // Collection()

$eml = new \Wicket\Entities\Emails([
	'address' => sprintf('alice_smith+%d@ind.ninja', rand(10000, 99999)),
	'primary' => true,
]);

$person = new \Wicket\Entities\People([
	'given_name'  => sprintf('Alice%d', rand(10000, 99999)),
	'family_name' => sprintf('Smith%d', rand(10000, 99999)),
]);

$person->attach($eml);    // related entities can be `attached`

$new_person = $client->people->create($person, $org);
```
