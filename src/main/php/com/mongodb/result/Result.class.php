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
  public function hashCode() { return static::class[0].Objects::hashOf($this->result); }

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
