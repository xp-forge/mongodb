<?php namespace com\mongodb\result;

use com\mongodb\Document;

/**
 * The result of a `findAndModify` operation
 *
 * @see  com.mongodb.Collection::modify
 * @see  com.mongodb.Collection::remove
 * @see  https://www.mongodb.com/docs/manual/reference/command/findAndModify/
 * @test com.mongodb.unittest.result.ModificationTest
 */
class Modification extends Result {
  const REMOVED= 'removed';
  const CREATED= 'created';
  const UPDATED= 'updated';

  /** Returns number of modified documents */
  public function modified(): int { return $this->result['lastErrorObject']['n']; }

  /** Returns kind of modification: created, updated or removed. */
  public function kind(): string {
    if (isset($this->result['lastErrorObject']['upserted'])) {
      return self::CREATED;
    } else if (isset($this->result['lastErrorObject']['updatedExisting'])) {
      return self::UPDATED;
    } else {
      return self::REMOVED;
    }
  }

  /**
   * Returns the upserted ID, if any
   *
   * @return ?(string|com.mongodb.ObjectId)
   */
  public function upserted() {
    return $this->result['lastErrorObject']['upserted'] ?? null;
  }

  /**
   * Returns the document
   *
   * @return ?com.mongodb.Document
   */
  public function document() {
    return isset($this->result['value']) ? new Document($this->result['value']) : null;
  }
}
