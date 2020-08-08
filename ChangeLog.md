MongoDB for XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

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
