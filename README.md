# Gigantier PHP SDK

[![build](https://travis-ci.org/Gigantier/php-sdk.svg?branch=master)](https://travis-ci.org/Gigantier/php-sdk)

> SDK to connect your PHP app to Giganter API.

## Installation

You can install the package manually or by adding it to your `composer.json`:

```
{
  "require": {
    "gigantier/php-sdk": "^1.0.0"
  }
}
```

## Usage

To get started, instantiate a new Gigantier client with your credentials.

> **Note:** This requires a [Gigantier](http://gigantier.com) account.

```php
$config = new Config();
$config->clientId = '{your_client_id}';
$config->clientSecret = '{your_client_secret}';
$config->scope = '{api_scope}';

$gigantier = new Gigantier\Client($config);
```

Check out the [API reference](https://docs.gigantier.com/?php) to learn more about authenticating and the available endpoints.

### API Call

Here is an example of api call:

```php
$response = $gigantier->call("/Category/list");
if ($response->isError()) {
  // Error response
} else {
  // Ok response
}
```

### Authentication

Some endpoints need the user to be authenticated, once they are obtained, the ```authenticate()``` method must be called:

```php
$response = $gigantier->authenticate("foo@test.com", "1111111");
if ($response->isError()) {
  // Error response
} else {
  // Ok response
}
```

### Authenticated API Call

Here is an example of and authenticated api call. Keep in mind that the method ```authenticate()``` must be executed first:

```php
$response = $gigantier->authenticatedCall('/User/me');
if ($response->isError()) {
  // Error response
} else {
  // Ok response
}
```

### Data Post

To perform data post you need to pass an array with that data to ```call()``` method:

```php
$data = array('name' => 'John', 'surname' => 'Doe');
$response = $gigantier->authenticatedCall('/User/me', $data);
if ($response->isError()) {
  // Error response
} else {
  // Ok response
}
```

## Test

Before running the tests execute:

```bash
composer install
```

Then you can run the tests:

```bash
vendor/bin/phpunit
```

To generate a coverage report:

```bash
vendor/bin/phpunit --coverage-html ./coverage
```

## Contributing

Thank you for considering contributing to Gigantier PHP SDK.