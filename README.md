## Laravel/Lumen 5 service provider for Understand.io

[![Latest Version on Packagist](https://img.shields.io/packagist/v/understand/understand-laravel5.svg?style=flat-square)](https://packagist.org/packages/understand/understand-laravel5)
[![Quality Score](https://img.shields.io/scrutinizer/g/understand/understand-laravel5.svg?style=flat-square)](https://scrutinizer-ci.com/g/understand/understand-laravel5)
[![Total Downloads](https://img.shields.io/packagist/dt/understand/understand-laravel5.svg?style=flat-square)](https://packagist.org/packages/understand/understand-laravel5)

### Introduction

This packages provides a full abstraction for Understand.io and provides extra features to improve Laravel/Lumen's default logging capabilities. It is essentially a wrapper around Laravel's event handler to take full advantage of Understand.io's data aggregation and analysis capabilities.

### Quick start (Laravel)

1. Add the package to your project
    
```
composer require understand/understand-laravel5
```

2. Add the ServiceProvider to the `providers` array in `config/app.php`
  
```php
Understand\UnderstandLaravel5\UnderstandLaravel5ServiceProvider::class,
```

3. Set your Understand.io input token in your `.env` file
  
```php
UNDERSTAND_ENABLED=true
UNDERSTAND_TOKEN=your-input-token-from-understand-io
```

4. Send your first error

```php 
// anywhere inside your Laravel app
\Log::error('Understand.io test error');
```

### Quick start (Lumen)

1. Add the package to your project
    
```
composer require understand/understand-laravel5
```

2. Register the ServiceProvider in `bootstrap/app.php`
  
```php
 $app->register(\Understand\UnderstandLaravel5\UnderstandLumenServiceProvider::class);
```

3. Set your Understand.io input token in your `.env` file
  
```php
UNDERSTAND_ENABLED=true
UNDERSTAND_TOKEN=your-input-token-from-understand-io
```

4. Send your first error

```php 
// anywhere inside your Laravel app
\Log::error('Understand.io test error');
```

- We recommend that you make use of a async handler - [How to send data asynchronously](#how-to-send-data-asynchronously)  
- If you are using Laravel 5.0 (`>= 5.0, < 5.1`) or Lumen (`>= 5.1, < 5.6`) versions, please read about - [How to report Laravel 5.0 (>= 5.0, < 5.1) / Lumen (>= 5.1, < 5.6) exceptions](#how-to-report-laravel-50--50--51--lumen--51--56-exceptions).
- For advanced configuration please read about - [Advanced configuration](#advanced-configuration)


### How to send events/logs

#### Laravel logs
By default, Laravel automatically stores its [logs](http://laravel.com/docs/errors#logging) in `storage/logs`. By using this package, your log data will also be sent to your Understand.io channel. This includes error and exception logs, as well as any log events that you have defined (for example, `Log::info('my custom log')`).

```php 
\Log::info('my message', ['my_custom_field' => 'my data']);
```
#### PHP/Laravel/Lumen exceptions
By default, all errors and exceptions with code fragments and stack traces will be sent to Understand.io. 

The following extra information will be collected:

| Type | Default | Config Key | Config Options |
| --- | --- | --- | --- |
| SQL queries | Enabled | `UNDERSTAND_SQL=` | `true` or `false` |
| SQL query values/bindings | Disabled | `UNDERSTAND_SQL_BINDINGS=` | `true` or `false` |
| HTTP request query string data | Enabled | `UNDERSTAND_QUERY_STRING=` | `true` or `false` |
| HTTP request form or JSON data | Enabled | `UNDERSTAND_POST_DATA=` | `true` or `false` |

Additionally, you can specify which HTTP request field values should not be sent to Understand.io.
By default, the following field values will be hidden: 
```
UNDERSTAND_HIDDEN_REQUEST_FIELDS=password,access_token,secret_key,token,access_key
```

If you wish you can publish the configuration file and make desired adjustments. See [Advanced configuration](#advanced-configuration)

### How to send data asynchronously

##### Async handler
By default each log event will be sent to Understand.io's api server directly after the event happens. If you generate a large number of logs, this could slow your app down and, in these scenarios, we recommend that you make use of an async handler. To do this, set the config parameter `UNDERSTAND_HANDLER` to `async` in your `.env` file.

```php
# Specify which handler to use - sync, queue or async. 
# 
# Note that the async handler will only work in systems where 
# the CURL command line tool is installed
UNDERSTAND_HANDLER=async
```

The async handler is supported in most systems - the only requirement is that the CURL command line tool is installed and functioning correctly. To check whether CURL is available on your system, execute following command in your console:

```
curl -h
```

If you see instructions on how to use CURL then your system has the CURL binary installed and you can use the ```async``` handler.

> Keep in mind that Laravel allows you to specify different configuration values in different environments. You could, for example, use the async handler in production and the sync handler in development.

### How to report Laravel 5.0 (`>= 5.0, < 5.1`) / Lumen (`>= 5.1, < 5.6`) exceptions 

Laravel's (`>= 5.0, < 5.1`) and Lumen (`>= 5.1, < 5.6`) exception logger doesn't use event dispatcher (https://github.com/laravel/framework/pull/10922) and that's why you need to add the following line to your `Handler.php` file (otherwise Laravel's exceptions will not be sent Understand.io).

- Open `app/Exceptions/Handler.php` and put this line `\UnderstandExceptionLogger::log($e)` inside `report` method.
  
  ```php
  public function report(Exception $e)
  {
      \UnderstandExceptionLogger::log($e);

      return parent::report($e);
  }
  ```
 
 
### Advanced Configuration

1. Publish configuration file

```
php artisan vendor:publish --provider="Understand\UnderstandLaravel5\UnderstandLaravel5ServiceProvider"
```

### Requirements 
##### UTF-8
This package uses the json_encode function, which only supports UTF-8 data, and you should therefore ensure that all of your data is correctly encoded. In the event that your log data contains non UTF-8 strings, then the json_encode function will not be able to serialize the data.

http://php.net/manual/en/function.json-encode.php

### License

The Laravel Understand.io service provider is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
