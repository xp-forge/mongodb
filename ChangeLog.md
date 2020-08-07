MongoDB for XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

## 0.4.0 / 2020-08-07

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
