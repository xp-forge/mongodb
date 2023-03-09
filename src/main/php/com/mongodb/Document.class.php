<?php namespace com\mongodb;

use ArrayAccess, Traversable, IteratorAggregate, ReturnTypeWillChange;
use lang\Value;
use util\Objects;

class Document implements Value, ArrayAccess, IteratorAggregate {
  private $properties;

  /** @param [:var] */
  public function __construct($properties= []) { $this->properties= $properties; }

  /** @return string|com.mongodb.ObjectId */
  public function id() { return $this->properties['_id'] ?: null; }

  /** @return [:var] */
  public function properties() { return $this->properties; }

  /**
   * Isset overlading
   *
   * @param  string $name
   * @return bool
   */
  #[ReturnTypeWillChange]
  public function offsetExists($name) {
    return isset($this->properties[$name]);
  }

  /**
   * Read access overloading
   *
   * @param  string $name
   * @return bool
   */
  #[ReturnTypeWillChange]
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
  #[ReturnTypeWillChange]
  public function offsetSet($name, $value) {
    $this->properties[$name]= $value;
  }

  /**
   * Unset overloading
   *
   * @param  string $name
   * @return void
   */
  #[ReturnTypeWillChange]
  public function offsetUnset($name) {
    unset($this->properties[$name]);
  }

  /**
   * Merge a given property with a given list or map
   *
   * @see    https://www.php.net/array_merge
   * @param  string $name
   * @param  iterable $from
   * @return self
   */
  public function merge($name, $from) {
    $prop= &$this->properties[$name];
    if (empty($prop)) {
      $prop= is_array($from) ? $from : iterator_to_array($from);
    } else if (0 === key($prop)) {
      foreach ($from as $value) $prop[]= $value;
    } else {
      foreach ($from as $key => $value) $prop[$key]= $value;
    }
    return $this;
  }

  /** Iterator over all properties */
  public function getIterator(): Traversable { yield from $this->properties; }

  /** @return string */
  public function hashCode() {
    return 'D'.Objects::hashOf($this->properties);
  }

  /** @return string */
  public function toString() {
    $s= nameof($this);

    $id= $this->properties['_id'] ?? null;
    if (null === $id) {
      $s.= '(-)';
    } else if ($id instanceof ObjectId) {
      $s.= '('.$id->string().')';
    } else {
      $s.= '('.Objects::stringOf($this->properties['_id']).')';
    }

    $s.= "@{\n";
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