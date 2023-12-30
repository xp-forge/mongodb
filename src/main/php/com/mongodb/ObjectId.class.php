<?php namespace com\mongodb;

use lang\{Value, IllegalArgumentException};

class ObjectId implements Value {
  private static $rand, $counter;
  private $string;

  static function __static() {
    self::$rand= random_bytes(5);
    self::$counter= random_int(0, 4294967294);  // uint32
  }

  /**
   * Creates a new Object ID from a given hex string
   *
   * @param  string $string
   * @throws lang.IllegalArgumentException
   */
  public function __construct($string) {
    if (!preg_match('/^[0-9a-f]{24}$/', $string)) {
      throw new IllegalArgumentException('Given object ID "'.$string.'" must consist of 24 hex characters');
    }

    $this->string= $string;
  }

  /**
   * Creates a new random object ID consisting of:
   *
   * - a 4-byte timestamp value (uses current time if omitted)
   * - a 5-byte random value
   * - a 3-byte incrementing counter, initialized to a random value
   *
   * @see  https://docs.mongodb.com/manual/reference/method/ObjectId/
   */
  public static function create(int $timestamp= null): self {
    $uint32= self::$counter > 4294967294 ? self::$counter= 0 : ++self::$counter;
    return new self(bin2hex(pack(
      'Na5aaa',
      null === $timestamp ? time() : $timestamp,
      self::$rand,
      chr($uint32 >> 16),
      chr($uint32 >> 8),
      chr($uint32)
    )));
  }

  /** @return string */
  public function string() { return $this->string; }

  /** @return string */
  public function __toString() { return $this->string; }

  /** @return string */
  public function hashCode() { return 'I'.$this->string; }

  /** @return string */
  public function toString() { return nameof($this).'('.$this->string.')'; }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->string <=> $value->string : 1;
  }

  /**
   * Test for equality
   *
   * @param  var $value
   * @return bool
   */
  public function equals($value) {
    return $value instanceof self ? 0 === ($this->string <=> $value->string) : false;
  }
}