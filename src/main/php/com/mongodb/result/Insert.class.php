<?php namespace com\mongodb\result;

use com\mongodb\ObjectId;
use lang\{IllegalStateException, Value};
use util\Objects;

class Insert implements Value {
  private $result, $ids;

  /**
   * Creates a new insert result
   *
   * @param  [:var] $result
   * @param  com.mongodb.ObjectId[] $ids
   */
  public function __construct($result, $ids) {
    $this->result= $result;
    $this->ids= $ids;
  }

  /** Returns number of inserted documents */
  public function inserted(): int { return $this->result['n']; }

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
    if (1 === $this->result['n']) return $this->ids[0];
    
    throw new IllegalStateException('Inserted more than one document');
  }

  /** @return string */
  public function hashCode() { return 'U'.Objects::hashOf($this->result); }

  /** @return string */
  public function toString() { return nameof($this).'@'.Objects::stringOf($this->result); }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? Objects::compare($this->result, $value->result) : 1;
  }
}
