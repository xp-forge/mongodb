<?php namespace com\mongodb\result;

use lang\{IllegalStateException, Value};
use util\Objects;

class Insert implements Value {
  private $count, $ids;

  public function __construct($count, $ids) {
    $this->count= $count;
    $this->ids= $ids;
  }

  public function count() { return $this->count; }

  public function ids() { return $this->ids; }

  public function id() {
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
