<?php namespace com\mongodb;

use ArrayAccess, Traversable, IteratorAggregate, ReturnTypeWillChange;
use lang\{Value, IndexOutOfBoundsException};
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
   * Read access overloading. Returns a reference!
   *
   * @param  string $name
   * @return var
   * @throws lang.IndexOutOfBoundsException
   */
  #[ReturnTypeWillChange]
  public function &offsetGet($name) {

    // Double-check with array_key_exists() should the property be null.
    if (isset($this->properties[$name]) || array_key_exists($name, $this->properties)) {
      return $this->properties[$name];
    }

    throw new IndexOutOfBoundsException('Undefined property "'.$name.'"');
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