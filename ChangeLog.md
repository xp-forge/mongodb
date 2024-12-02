MongoDB for XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

## 2.5.0 / 2024-12-02

* Merged PR #49: Set a timeout of 1 second when issuing a ping - @thekid

## 2.4.1 / 2024-11-27

* Fixed possible collisions in `ObjectId::create()` when used within a
  forked subprocess by including the process ID in the random value's
  calculation.
  (@thekid)

## 2.4.0 / 2024-10-14

* Merged PR #48: Extend error handling to include if a write was retried
  (@thekid)

## 2.3.1 / 2024-04-02

* Fixed "undefined index primary" when no primary is available - @thekid

## 2.3.0 / 2024-03-24

* Made compatible with XP 12 - @thekid

## 2.2.0 / 2023-12-30

* Merged PR #44: Add `equals()` methods for wrapper classes - @thekid
* Merged PR #45: Implement Collection `modify()` and `remove()` via
  https://www.mongodb.com/docs/manual/reference/command/findAndModify/
  (@thekid)
* Fixed `upsert()` inconsistency with `update()` and `delete()` in how
  it handles the *query* parameter
  (@thekid)

## 2.1.0 / 2023-09-10

* Merged PR #43: Retry "Exception: not primary" when writing (fixes #42)
  (@thekid)

## 2.0.0 / 2023-08-19

The second major release adds the possibility to pass additional options
to all commands, including read preference as well as read and write
concerns.

* Merged PR #40: Implement read and write concerns, implementing #11.
  See https://www.mongodb.com/docs/manual/reference/read-concern/ and
  https://www.mongodb.com/docs/manual/reference/write-concern/
  (@thekid)
* Merged PR #41: Remove deprecated Collection::command(), superseded in
  1.4.0 with the new `run()` method, see #21.
  (@thekid)
* Merged PR #39: Refactor all methods to receive options varargs. Passing
  additional options to methods such as *find* can be accomplished by
  creating `Options` instances.
  (@thekid)

## 1.15.0 / 2023-08-18

* Merged PR #38: Support authentication mechanism negotiation. This way,
  we default to using SCRAM-SHA-256 if the server supports it as mandated
  by the specification.
  (@thekid)

## 1.14.0 / 2023-08-17

* Merged PR #37: Implement SCRAM-SHA-256 authentication. Implements #8
  (@thekid)

## 1.13.0 / 2023-08-17

* Merged PR #36: Introduce Commands class which keeps all messages on the
  same connection, fixing #29 (*Error: cursor id "..." not found*). Note:
  This changes the low-level internal API inside the `io` package!
  (@thekid)

## 1.12.0 / 2023-08-09

* Merged PR #35: Return document properties by reference - @thekid

## 1.11.0 / 2023-04-11

* Merged PR #34: Make it possible to omit regex modifiers - @thekid

## 1.10.0 / 2023-03-05

* Introduced new base class `com.mongodb.CannotConnect` - @thekid
* Merged PR #32: Fix DNS errors, fixing issue #31 - @thekid
* Merged PR #30: Migrate to new testing library - @thekid

## 1.9.2 / 2023-01-15

* Added fix for #28 - reconnecting when using readPreference *nearest*
  fails with *Undefined array key ""* error.
  (@thekid)

## 1.9.1 / 2022-12-23

* Merged PR #27: Handle errors from `dns_get_record()` - @thekid

## 1.9.0 / 2022-12-18

* Merged PR #26: Add `MongoConnection::protocol()` - @thekid
* Fixed IPv6 handling in connection DSNs - @thekid

## 1.8.0 / 2022-11-20

* Merged PR #25: Implement `toString()` for collections and databases
  (@thekid)

## 1.7.0 / 2022-09-24

* Changed error message when an empty or malformed DSN is passed to the
  `Protocol` class' constructor to better indicate what has gone wrong
  (@thekid)

## 1.6.0 / 2022-09-10

* Merged PR #24: Add `Collection::upsert()` which calls the *update*
  command with `upsert: true`.
  (@thekid)

## 1.5.0 / 2022-09-01

* Added support for `socketTimeoutMS` parameter in connection string,
  defaulting to 60000 (1 minute, the default socket timeout).
  See https://www.mongodb.com/docs/manual/reference/connection-string/
  (@thekid)

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
