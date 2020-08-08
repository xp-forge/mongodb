<?php namespace com\mongodb\result;

use lang\Value;
use util\Objects;

class Update implements Value {
  private $result;

  /**
   * Creates a new update result
   *
   * @param  [:var] $result
   */
  public function __construct($result) {
    $this->result= $result;
  }

  /** Returns number of matched documents */
  public function matched(): int { return $this->result['n']; }

  /** Returns number of modified documents */
  public function modified(): int { return $this->result['nModified']; }

  /** @return string */
  public function hashCode() { return 'I'.Objects::hashOf($this->result); }

  /** @return string */
  public function toString() {
    return nameof($this).'@'.Objects::stringOf($this->result);
  }

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
