MongoDB for XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

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
