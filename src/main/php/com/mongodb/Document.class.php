<?php namespace com\mongodb;

use lang\Value;
use util\Objects;

class Document implements Value, \ArrayAccess, \IteratorAggregate {
  private $properties;

  /** @param [:var] */
  public function __construct($properties= []) { $this->properties= $properties; }

  /** @return ?com.mongodb.ObjectId */
  public function id() { return $this->properties['_id'] ?: null; }

  /** @return [:var] */
  public function properties() { return $this->properties; }

  /**
   * Isset overlading
   *
   * @param  string $name
   * @return bool
   */
  public function offsetExists($name) {
    return isset($this->properties[$name]);
  }

  /**
   * Read access overloading
   *
   * @param  string $name
   * @return bool
   */
  public function offsetGet($name) {
    return $this->properties[$name];
  }

  /**
   * Write access overloading
   *
   * @param  string $name
   * @param  var $value
   * @return void
   */
  public function offsetSet($name, $value) {
    $this->properties[$name]= $value;
  }

  /**
   * Unset overloading
   *
   * @param  string $name
   * @return void
   */
  public function offsetUnset($name) {
    unset($this->properties[$name]);
  }

  /** @return iterable */
  public function getIterator() { yield from $this->properties; }

  /** @return string */
  public function hashCode() {
    return 'D'.Objects::hashOf($this->properties);
  }

  /** @return string */
  public function toString() {
    $s= nameof($this).(isset($this->properties['_id']) ? '('.$this->properties['_id']->string().')' : '(-)')."@{\n";
    foreach ($this->properties as $key => $value) {
      '_id' === $key || $s.= '  '.$key.': '.Objects::stringOf($value, '  ')."\n";
    }
    return $s.'}';
  }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? Objects::compare($this->properties, $value->properties) : 1;
  }
}