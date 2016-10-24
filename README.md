Tao
===

The Tao of Microservices is an application layer for rapid data access.

Install
-------

To install the library simply include it in your `composer.json` file.

Settings
--------

The data access payer expects a `settings.ini` file at the base of the your 
service directory. This file should organize it's data into sections, with the 
specific settings for each.

To connect to a database, just add a `[database]` section to your INI file, 
with the following properties:

```
[database]

dsn = ...
username = ...
password = ...
```

Usage
-----

To register a service simply call the static `Tao\Service::init()` method, and 
pass it a key => value array of action names and their corresponding callbacks.

Then, simply call the `run()` method to register the actions and run the 
service, for example:

```php
Tao\Service::init([
    'example' => function ($action) {
        // action logic here
        return $action;
    }
])->run();
```

To use the data access layer call the static `Tao\Action::init()` from within 
your logic, and pass it the `$action` instance. This will provide the database 
connection and helper methods to easily populate your transport. Then, just 
call the `run()` method to execute and return the `$action` instance, for 
example:

```php
return Tao\action::init($action)->entity(['text' => 'Hello World'])->run();
```

Both the `entity()` and `collection()` methods can receive raw data, or they can 
take a string, which they assume to be an SQL function, for example:

```php
return Tao\action::init($action)->entity('do_something')->run();
```

A set of arguments to pass to that function may be provided as the second 
argument. If none is provided it assumed that all parameters passed to the 
actions are also for the SQL function. Note that the argument are prepended 
with "p_" by default.

Copyright
---------

Copyright (c) 2016-2017 LightHorse Consulting, LLC. All rights reserved.
