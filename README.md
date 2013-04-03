CartoDB SQL API v2 Client for PHP by Simbiotica 
===============================================


Usage
-----

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
