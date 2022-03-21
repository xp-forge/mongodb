<?php namespace com\mongodb\result;

use lang\Value;
use util\Objects;

abstract class Result implements Value {
  protected $result;

  /**
   * Creates a new update result
   *
   * @param  [:var] $result
   */
  public function __construct($result) {
    $this->result= $result;
  }

  /** @return string */
  public function toString() { return nameof($this).'@'.Objects::stringOf($this->result); }

  /** @return string */
  public function hashCode() {
    return static::class[strlen(__NAMESPACE__) + 1].crc32(print_r($this->result, true));
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
