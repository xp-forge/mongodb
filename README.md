MongoDB connectivity
====================

[![Build Status on TravisCI](https://secure.travis-ci.org/xp-forge/mongodb.svg)](http://travis-ci.org/xp-forge/mongodb)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.png)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/mongodb/version.png)](https://packagist.org/packages/xp-forge/mongodb)

This library implements MongoDB connectivity via its binary protocol. It has no dependencies on the PHP extension.

Examples
--------
Finding documents inside a collection:

```php
use com\mongodb\MongoConnection;
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
 
$cursor= $c->collection('test.products')->find();
foreach ($cursor as $document) {
  Console::writeLine('>> ', $document);
}
```

Inserting a document into a collection:

```php
use com\mongodb\{MongoConnection, Document};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');

$result= $c->collection('test.products')->insert(new Document([
  'name' => 'Test',
  'qty'  => 10,
  'tags' => ['new', 'tested'],
]));
Console::writeLine('>> ', $result);
```

Updating documents:

```php
use com\mongodb\{MongoConnection, ObjectId, Operations};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');

$inc= new Operations(['$inc' => ['qty' => 1]]);

// Select a single document for updating by its ID
$result= $c->collection('test.products')->update($inc->select(new ObjectId('...')));

// Apply to all documents matchig a given filter
$result= $c->collection('test.products')->update($inc->where(['name' => 'Test']));

Console::writeLine('>> ', $result);
```

Authentication
--------------
To authenticate, pass username and password via the connection string, e.g. `mongodb://user:pass@localhost`. The authentication source defaults to *admin* but can be set by supplying a path, e.g. `mongodb://user:pass@localhost/test`.

Currently, *SCRAM-SHA-1* is the only supported authentication mechanism.

Aggregation
-----------
The `Collection` class also features aggregation methods:

* `count($filter= [])`
* `distinct($key, $filter= [])`
* `aggregate($pipeline)`

See https://docs.mongodb.com/manual/reference/command/nav-aggregation/

Type mapping
------------
All builtin types are mapped to their BSON equivalents. In addition, the following type mappings are used:

* `util.Date` => UTC datetime
* `util.Bytes` => Binary data
* `util.UUID` => UUID binary data
* `com.mongodb.Int64` => 64-bit integer
* `com.mongodb.Decimal128` => 128-bit decimal
* `com.mongodb.ObjectId` => Object ID
* `com.mongodb.Timestamp` => Timestamp
* `com.mongodb.Regex` => Regular expression

The deprecated types of the BSON spec are not supported, see http://bsonspec.org/spec.html