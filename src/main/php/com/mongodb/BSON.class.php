<?php namespace com\mongodb;

use lang\{FormatException, IllegalArgumentException};
use util\{Bytes, Date, TimeZone, UUID};

/** @see http://bsonspec.org/spec.html */
class BSON {
  private static $UTC;

  static function __static() {
    self::$UTC= TimeZone::getByName('UTC');
  }

  public function sections($value) {
    $bytes= '';
    foreach ($value as $key => $element) {
      $bytes.= $this->bytes($key, $element);
    }
    return pack('V', strlen($bytes) + 5).$bytes."\x00";
  }

  /**
   * Encode a value to bytes according to BSON specification
   *
   * @param  string $name
   * @param  var $value
   * @return string
   * @throws lang.IllegalArgumentException
   */
  public function bytes($name, $value) {
    if (true === $value) {
      return "\x08".$name."\x00\x01";
    } else if (false === $value) {
      return "\x08".$name."\x00\x00";
    } else if (null === $value) {
      return "\x0a".$name;
    } else if ($value instanceof Bytes) {
      return "\x05".$name."\x00".pack('Vx', strlen($value)).$value;
    } else if ($value instanceof Date) {
      return "\x09".$name."\x00".pack('P', $value->getTime() * 1000);
    } else if ($value instanceof UUID) {
      return "\x05".$name."\x00".pack('Vc', 16, 4).$value->getBytes();
    } else if ($value instanceof ObjectId) {
      return "\x07".$name."\x00".hex2bin($value->string());
    } else if ($value instanceof Timestamp) {
      return "\x11".$name."\x00".pack('VV', $value->increment(), $value->seconds());
    } else if ($value instanceof Int64) {
      return "\x12".$name."\x00".pack('P', $value->number());
    } else if ($value instanceof Regex) {
      return "\x0b".$name."\x00".$value->pattern()."\x00".$value->modifiers()."\x00";
    } else if ($value instanceof Document || $value instanceof \StdClass) {
      return "\x03".$name."\x00".$this->sections($value);
    } else if (is_string($value)) {
      return "\x02".$name."\x00".pack('V', strlen($value) + 1).$value."\x00";
    } else if (is_int($value)) {
      return "\x10".$name."\x00".pack('V', $value);
    } else if (is_float($value)) {
      return "\x01".$name."\x00".pack('d', $value);
    } else if (is_array($value)) {
      $id= 0 === key($value) || empty($value) ? "\x04" : "\x03";
      return $id.$name."\x00".$this->sections($value);
    }

    throw new IllegalArgumentException('Cannot encode value '.$name.' of type '.typeof($value));
  }

  public function document($input, &$offset) {
    $length= unpack('V', substr($input, $offset, 4))[1];
    $offset+= 4;

    $document= [];
    while ("\x00" !== $input[$offset]) {
      $name= substr($input, $offset + 1, strcspn($input, "\x00", $offset + 1));
      $value= $this->value($name, $input, $offset);
      $document[$name]= $value;
    }

    $offset++;
    return $document;
  }

  /**
   * Decode given bytes to a value according to BSON specification
   *
   * @param  string $name
   * @param  string $bytes
   * @return var
   * @throws lang.FormatException
   */
  public function value($name, $bytes, &$offset) {
    $kind= $bytes[$offset];
    $offset+= strlen($name) + 2;

    if ("\x01" === $kind) {           // 64-bit binary floating point
      $bytes= substr($bytes, $offset, 8);
      $offset+= 8;
      return unpack('d', $bytes)[1];      
    } else if ("\x02" === $kind) {    // UTF-8 string
      $length= unpack('V', substr($bytes, $offset, 4))[1];
      $value= substr($bytes, $offset + 4, $length - 1);
      $offset+= $length + 4;
      return $value;
    } else if ("\x03" === $kind) {    // Embedded document
      $d= $this->document($bytes, $offset);
      return empty($d) ? (object)[] : $d;
    } else if ("\x04" === $kind) {    // Array
      return $this->document($bytes, $offset);
    } else if ("\x05" === $kind) {    // Binary data
      $binary= unpack('Vlength/csubtype', substr($bytes, $offset, 5));
      $value= substr($bytes, $offset + 5, $binary['length']);
      $offset+= $binary['length'] + 5;

      switch ($binary['subtype']) {
        case 0: return new Bytes($value);
        case 4: return new UUID(new Bytes($value));
        default: throw new FormatException('Cannot handle binary subtype '.$binary['subtype']);
      }
    } else if ("\x07" === $kind) {    // ObjectId
      $id= substr($bytes, $offset, 12);
      $offset+= 12;
      return new ObjectId(bin2hex($id));
    } else if ("\x08" === $kind) {    // Boolean "false" / "true"
      $byte= $bytes[$offset];
      $offset+= 1;
      return "\x01" === $byte;
    } else if ("\x09" === $kind) {    // UTC datetime
      $bytes= substr($bytes, $offset, 8);
      $offset+= 8;
      return new Date(intval(unpack('P', $bytes)[1] / 1000), self::$UTC);
    } else if ("\x11" === $kind) {    // Timestamp
      $binary= unpack('Vincrement/Vseconds', substr($bytes, $offset, 8));
      $offset+= 8;
      return new Timestamp($binary['seconds'], $binary['increment']);
    } else if ("\x0a" === $kind) {    // Null value
      return null;
    } else if ("\x10" === $kind) {    // 32-bit integer
      $bytes= substr($bytes, $offset, 4);
      $offset+= 4;
      $value= unpack('V', $bytes)[1];
      return $value > 0x7fffffff ? $value - 0x100000000 : $value;
    } else if ("\x12" === $kind) {    // 64-bit integer
      $bytes= substr($bytes, $offset, 8);
      $offset+= 8;
      return new Int64(unpack('P', $bytes)[1]);
    }

    throw new FormatException('Unknown type 0x'.dechex(ord($kind)).': '.substr($bytes, $offset));
  }
}