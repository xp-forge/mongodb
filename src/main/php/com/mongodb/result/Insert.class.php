<?php namespace com\mongodb\result;

use lang\IllegalStateException;
use util\Objects;

class Insert extends Result {
  private $ids;

  /**
   * Creates a new insert result
   *
   * @param  [:var] $result
   * @param  (string|com.mongodb.ObjectId)[] $ids
   */
  public function __construct($result, $ids) {
    parent::__construct($result);
    $this->ids= $ids;
  }

  /** Returns number of inserted documents */
  public function inserted(): int { return $this->result['n']; }

  /**
   * If multiple documents were inserted, use this method to retrieve
   * their object IDs in the same order as they were passed in.
   *
   * @return (string|com.mongodb.ObjectId)[]
   */
  public function ids(): array { return $this->ids; }

  /**
   * If a single document was inserted, use this shortcut for retrieving
   * its object ID.
   *
   * @return string|com.mongodb.ObjectId
   * @throws lang.IllegalStateException
   */
  public function id() {
    if (1 === $this->result['n']) return $this->ids[0];
    
    throw new IllegalStateException('Inserted more than one document');
  }
}
