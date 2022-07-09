MongoDB for XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

## 1.4.0 / 2022-07-09

* Merged PR #23: Add `MongoConnection::run()` to run commands in the
  *admin* database, e.g. *ping*.
  (@thekid)
* Merged PR #22: Add `Cursor::all()`. This is equivalent to iterating and
  storing all documents in an array but more concise.
  (@thekid)
* Merged PR #21: Add `Collection::run()` to run commands. This deprecates
  the *command* method, which will be removed in a future release.
  (@thekid)

## 1.3.0 / 2022-03-29

* Merged PR #19: Pick a random secondary, improving load distribution
  (@thekid)

## 1.2.0 / 2022-03-27

* Fixed `com.mongodb.result.Cursor::first()` to raise a meaningful error
  message when cursor has been forwarded
  (@thekid)
* Added `com.mongodb.result.Cursor::present()` to check whether a cursor
  represents a non-empty result
  (@thekid)

## 1.1.1 / 2022-03-26

* Fixed reading large documents - @thekid

## 1.1.0 / 2022-03-26

* Merged PR #17: Add support for MinKey and MaxKey types - @thekid
* Merged PR #16: Code support - @thekid
* Simplified EOF handling in `Connection::send()` - @thekid
* Renamed Protocol class' connection() method to `dsn()` - @thekid

## 1.0.0 / 2022-03-25

This first major release supports working with replica sets as well as
with standalone MongoDB servers. There is no support for read and write
concerns yet, and no support for client-side encryption.

* Fixed `com.mongodb.Decimal128` for large negative numbers - @thekid
* Fixed `com.mongodb.NoSuitableCandidate::candidates()` - @thekid
* Fixed equality comparisons for `com.mongodb.result.Cursor` - @thekid
* Increased code coverage significantly by adding a variety of unittests
  (@thekid)

## 0.11.0 / 2022-03-20

* Merged PR #15: Change streams: Watch databases and collections for
  changes. See https://docs.mongodb.com/manual/changeStreams/
  (@thekid)
* Changed `Collection::update()` to update all documents the query finds
  in the same way *delete()* does.
  (@thekid)

## 0.10.0 / 2022-03-20

* Merged PR #13: Implement multi-document transactions - @thekid

## 0.9.1 / 2022-03-19

* Fixed operation errors causing reconnection - @thekid
* Normalized databases enumeration between MongoDB versions - @thekid
* Fixed `com.mongodb.MongoConnection::databases()` - @thekid
* Fixed `com.mongodb.Database::collections()` - @thekid

## 0.9.0 / 2022-03-19

* Implemented #9: Reconnect - according to specification, by checking
  a connection with the *ping* command if it has been not used for a
  defined number of seconds (defaulting to 5)
  (@thekid)
* Merged PR #10: Sessions. Adds support for passing sessions to all of
  the database and collection methods.
  (@thekid)
* Merged PR #5: Add support for mongodb+srv. This adds support for DNS
  seed lists, as well as reading from and writing to separate connections
  based on the read preference supplied.
  (@thekid)

## 0.8.0 / 2022-03-10

* Changed implementation to raise `peer.ProtocolException` instead of
  low-level socket errors
  (@thekid)

## 0.7.2 / 2021-10-25

* Fixed #6: Exception com.mongodb.Error (#40415:Location40415 "BSON field
  'saslContinue.done' is an unknown field.")
  (@thekid)
* Fixed #7: Array to string conversion error in `Document::toString()`
  (@thekid)

## 0.7.1 / 2021-10-21

* Made library compatible with XP 11 - @thekid

## 0.7.0 / 2021-09-16

* Fixed PHP 8.1 compatibility for IteratorAggregate / ArrayAccess return
  type declarations
  (@thekid)
* Fixed issue #4: Cannot handle binary subtype 3 - @thekid
* Enable SSL/TLS if `ssl=true` or `tls=true` is passed in the connection
  string parameters; implements feature request #2
  (@thekid)
* Made `params` for `Collection::command()` optional - @thekid

## 0.6.0 / 2020-08-29

* Added `Cursor::first()` to return the first document (or NULL).
  (@thekid)
* Made `Collection::find()` also accept IDs to find single documents
  (@thekid)
* Added `Collection::command()` to run arbitrary commands - @thekid
* Simplified usage of collection methods `update()` and `delete()`
  (@thekid)

## 0.5.3 / 2020-08-29

* Fixed reading to ensure we have enough bytes when reading packets
  (@thekid)

## 0.5.2 / 2020-08-08

* Fixed error #22 (InvalidBSON) when using `NULL` values - @thekid

## 0.5.1 / 2020-08-08

* Rewrote `count()` and `distinct()` to use aggregation pipelines. See
  https://docs.mongodb.com/manual/core/transactions/#count-operation and
  https://docs.mongodb.com/manual/core/transactions/#distinct-operation
  (@thekid)

## 0.5.0 / 2020-08-08

* Added new methods `Collection::distinct()` and `Collection::count()`
  (@thekid)
* Added new method `Collection::aggregate()` to perform aggregations, see
  https://docs.mongodb.com/manual/reference/command/aggregate/ and
  https://docs.mongodb.com/manual/reference/operator/aggregation/lookup/
  (@thekid)
* Fixed all places where `_id` was assumed to always hold `ObjectId`s.
  (@thekid)

## 0.4.0 / 2020-08-08

* Merged PR #1: Implement update operation. Currently supports update operator
  expressions, replacement documents and pipelines will be added later.
  (@thekid)
* Changed `insert()` to generate and return object IDs if not supplied in
  passed documents. Consistent with how most MongoDB drivers handle this, see
  https://docs.mongodb.com/manual/reference/method/db.collection.insert/#id-field
  (@thekid)

## 0.3.1 / 2020-08-06

* Fixed large decimal128 number parsing from a string - @thekid

## 0.3.0 / 2020-08-05

* Added `Decimal128` support as specified in http://bsonspec.org/spec.html
  (@thekid)

## 0.2.0 / 2020-08-04

* Added `Database::collections()` method to list collections in a database
  (@thekid)
* Added `MongoConnection::databases()` method which lists all databases
  (@thekid)
* Added support for regular expression, UUID and timestamp types
  (@thekid)

## 0.1.0 / 2020-08-04

* First public release - (@thekid)
