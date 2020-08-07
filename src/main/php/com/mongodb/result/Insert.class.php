<?php namespace com\mongodb\result;

use com\mongodb\ObjectId;
use lang\{IllegalStateException, Value};
use util\Objects;

class Insert implements Value {
  private $count, $ids;

  /**
   * Creates a new insert result
   *
   * @param  int $count
   * @param  com.mongodb.ObjectId[] $ids
   */
  public function __construct($count, $ids) {
    $this->count= $count;
    $this->ids= $ids;
  }

  /** Returns number of inserted documents */
  public function count(): int { return $this->count; }

  /**
   * If multiple documents were inserted, use this method to retrieve
   * their object IDs in the same order as they were passed in.
   *
   * @return com.mongodb.ObjectId[]
   */
  public function ids(): array { return $this->ids; }

  /**
   * If a single document was inserted, use this shortcut for retrieving
   * its object ID.
   *
   * @return com.mongodb.ObjectId
   * @throws lang.IllegalStateException
   */
  public function id(): ObjectId {
    if (1 === $this->count) return $this->ids[0];
    
    throw new IllegalStateException('Inserted more than one document');
  }

  /** @return string */
  public function hashCode() { return 'I'.Objects::hashOf($this->ids); }

  /** @return string */
  public function toString() {
    return nameof($this).'#'.$this->count.Objects::stringOf($this->ids);
  }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? Objects::compare($this->ids, $value->ids) : 1;
  }
}
