<?php namespace com\mongodb\io;

use lang\Value;
use util\Comparison;

/** 
 * Compression negotiation and selection.
 * 
 * @see   https://github.com/mongodb/specifications/blob/master/source/compression/OP_COMPRESSED.md
 * @test  com.mongodb.unittest.CompressionTest
 */
class Compression implements Value {
  use Comparison;

  private static $negotiable= [];
  private $compressors;

  static function __static() {
    extension_loaded('zlib') && self::$negotiable['zlib']= fn($options) => new Zlib($options['zlibCompressionLevel'] ?? -1);
    extension_loaded('zstd') && self::$negotiable['zstd']= fn($options) => new Zstd($options['zstdCompressionLevel'] ?? -1);
  }

  /** @param [:com.mongodb.io.Compressor] $compressors */
  public function __construct(array $compressors= []) {
    $this->compressors= $compressors;
  }

  /** Registers a given compressor */
  public function with(Compressor $compressor): self {
    $this->compressors[$compressor->id]= $compressor;
    return $this;
  }

  /**
   * Negotiate compression. Returns NULL if no compressors apply.
   * 
   * @param  string[] $server
   * @param  [:string] $options
   * @return ?self
   */
  public static function negotiate($server, $options= []) {
    $negotiated= [];
    foreach ($server as $preference) {
      if ($new= self::$negotiable[$preference] ?? null) {
        $compressor= $new($options);
        $negotiated[$compressor->id]= $compressor;
      }
    }
    return $negotiated ? new self($negotiated) : null;
  }

  /**
   * Selects the compressor for a given ID. Returns NULL if compressor by this
   * ID is present.
   *
   * @param  int $id
   * @return ?com.mongodb.io.Compressor
   */
  public function select($id) {
    return $this->compressors[$id] ?? null;
  }

  /**
   * Returns compressor for given input sections and length. Returns NULL if no
   * compression should be used.
   *
   * @param  [:var] $sectionts
   * @param  int $length
   * @return ?com.mongodb.io.Compressor
   */
  public function for($sections, $length) {
    if (empty($this->compressors) || (
      isset($sections['hello']) ||
      isset($sections['isMaster']) ||
      isset($sections['saslStart']) ||
      isset($sections['saslContinue']) ||
      isset($sections['getnonce']) ||
      isset($sections['authenticate']) ||
      isset($sections['createUser']) ||
      isset($sections['updateUser']) ||
      isset($sections['copydbSaslStart']) ||
      isset($sections['copydbgetnonce']) ||
      isset($sections['copydb'])
    )) return null;

    // Currently always select the first compressor negotiated
    return current($this->compressors);
  }

  /** @return string */
  public function toString() {
    $s= nameof($this)."@[\n";
    foreach ($this->compressors as $compressor) {
      $s.= '  '.$compressor->toString()."\n";
    }
    return $s.']';
  }
}