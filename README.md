MongoDB connectivity
====================

[![Build status on GitHub](https://github.com/xp-forge/mongodb/workflows/Tests/badge.svg)](https://github.com/xp-forge/mongodb/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/mongodb/version.png)](https://packagist.org/packages/xp-forge/mongodb)

This library implements MongoDB connectivity via its binary protocol. It has no dependencies on the PHP extension.

Examples
--------
Finding documents inside a collection:

```php
use com\mongodb\{MongoConnection, ObjectId};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
$id= new ObjectId(...);

// Find all documents
$cursor= $c->collection('test.products')->find();

// Find document with the specified ID
$cursor= $c->collection('test.products')->find($id);

// Find all documents with a name of "Test"
$cursor= $c->collection('test.products')->find(['name' => 'Test']);

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
use com\mongodb\{MongoConnection, ObjectId};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
$id= new ObjectId(...);

// Select a single document for updating by its ID
$result= $c->collection('test.products')->update($id, ['$inc' => ['qty' => 1]]);

// Apply to all documents matchig a given filter
$result= $c->collection('test.products')->update(['name' => 'Test'], ['$inc' => ['qty' => 1]]);

Console::writeLine('>> ', $result);
```

Deleting documents:

```php
use com\mongodb\{MongoConnection, ObjectId};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
$id= new ObjectId(...);

// Select a single document to be removed
$result= $c->collection('test.products')->delete($id);

// Remove all documents matchig a given filter
$result= $c->collection('test.products')->delete(['name' => 'Test']);

Console::writeLine('>> ', $result);
```

*Note: All of the above have used the `collection()` shortcut which is equivalent to chaining `database('test')->collection('products')`.*

Authentication
--------------
To authenticate, pass username and password via the connection string, e.g. `mongodb://user:pass@localhost`. The authentication source defaults to *admin* but can be set by supplying a path, e.g. `mongodb://user:pass@localhost/test`.

Currently, *SCRAM-SHA-1* is the only supported authentication mechanism.

SSL / TLS
---------
To connect via SSL / TLS, pass `ssl=true` as connection string parameters, e.g.:

```php
use com\mongodb\MongoConnection;

$c= new MongoConnection('mongodb://localhost?ssl=true');

// Explicit call to connect, can be omitted when using collection()
$c->connect();
```

Aggregation
-----------
The `Collection` class also features aggregation methods:

* `count($filter= [])`
* `distinct($key, $filter= [])`
* `aggregate($pipeline)`

See https://docs.mongodb.com/manual/reference/command/nav-aggregation/

Sessions
--------
Using a causally consistent session, an application can read its own writes and is guaranteed monotonic reads, even when reading from replica set secondaries.

```php
use com\mongodb\{MongoConnection, ObjectId, Operations};
use util\cmd\Console;

$c= new MongoConnection('mongodb://localhost');
$session= $c->session();

$id= new ObjectId('...');
$collection= $c->collection('test.products');
$collection->update((new Operations(['$set' => ['qty' => 1]]))->select($id), $session);

// Will read the updated document
$updated= $collection->with()->select($id, $session)->first();
```

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