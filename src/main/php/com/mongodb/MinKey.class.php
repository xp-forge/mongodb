<?php namespace com\mongodb;

use lang\Value;

class MinKey implements Value {

  /** @return string */
  public function hashCode() { return 'KFF'; }

  /** @return string */
  public function toString() { return nameof($this); }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? 0 : 1;
  }
}