CartoDB SQL API v2 Client for PHP by Simbiotica 
===============================================

About
-----

A easy to use PHP client for CartoDB's SQL API v2, using OAuth or API key authentication.


Installation
------------

This library requires composer, a package manager for PHP.
Just add it to your composer.json

```js
{
    "require": {
        "simbiotica/cartodbclient-php": "*"
    }
}
```

and install it using the command:

``` bash
$ php composer.phar update simbiotica/cartodbclient-php
```

This will install not only this library, but the required dependencies.


Usage - OAuth
-------------

Currently two types of connections are supported: PrivateConnection and PublicConnection.
Public connections are a subset of Private ones, as they can be established
to any public table, with reduced privileges and configuration requirements

Additionally, TokenStorageInterface implementation is required to presist the OAuth Token
across requests. We provide SessionStorage and FileStorage, but feel free to
implement your own.

For reference, check the Connection class, where you'll find all the handy functions
you'll need. There's also a runSql() function for everything that's not covered
by existing functions.

All reponses are wrapped as a Payload object, which holds the formated answer,
request metadata and a couple of utilities to handle the result.

No actual demo is provided, but you can view a sort-of-example inside the /tests
folder. Remember to fill in your data before trying it.

Usage - API Key
---------------

Usage with just API key is less secure, but equaly functional and easier to setup.

All connections are supported by the Connection class, which accepts your domain
and, optionally, you API key. If you don't provide it, you will have limited access
to the tables.

For reference, check the Connection class, where you'll find all the handy functions
you'll need. There's also a runSql() function for everything that's not covered
by existing functions.

All reponses are wrapped as a Payload object, which holds the formated answer,
request metadata and a couple of utilities to handle the result.

No actual demo is provided, but you can view a sort-of-example inside the /tests
folder. Remember to fill in your data before trying it.
