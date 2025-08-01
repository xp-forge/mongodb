<?php namespace com\mongodb\unittest;

use com\mongodb\io\{Compression, Compressor, Zlib, Zstd};
use test\verify\Runtime;
use test\{Assert, Before, Test, Values};

class CompressionTest {
  private $compressor;

  #[Before]
  public function compressor() {
    $this->compressor= new class() extends Compressor {
      public $id= 9;
      public function compress($data) { /** Not implemented */ }
      public function decompress($compressed) { /** Not implemented */ }
    };
  }


  #[Test]
  public function can_create() {
    new Compression();
  }

  #[Test]
  public function select() {
    Assert::equals($this->compressor, (new Compression())->with($this->compressor)->select($this->compressor->id));
  }

  #[Test]
  public function select_empty() {
    Assert::null((new Compression())->select($this->compressor->id));
  }

  #[Test]
  public function select_non_existant() {
    Assert::null((new Compression())->with($this->compressor)->select(2));
  }

  #[Test]
  public function not_for_hello() {
    Assert::null((new Compression())->with($this->compressor)->for(['hello' => 1], 128));
  }

  #[Test]
  public function for_insert() {
    Assert::equals($this->compressor, (new Compression())->with($this->compressor)->for(['insert' => 'collection'], 1024));
  }

  #[Test]
  public function negotiate_empty() {
    Assert::null(Compression::negotiate([]));
  }

  #[Test]
  public function negotiate_unsupported() {
    Assert::null(Compression::negotiate(['unsupported']));
  }

  #[Test, Runtime(extensions: ['zlib'])]
  public function negotiate_zlib() {
    Assert::instance(Zlib::class, Compression::negotiate(['unsupported', 'zlib'])->select(2));
  }

  #[Test, Runtime(extensions: ['zlib']), Values([[[], -1], [['zlibCompressionLevel' => 6], 6]])]
  public function negotiate_zlib_with($options, $level) {
    $compressor= Compression::negotiate(['zlib'], $options)->select(2);

    Assert::instance(Zlib::class, $compressor);
    Assert::equals($level, $compressor->level);
  }

  #[Test, Runtime(extensions: ['zstd'])]
  public function negotiate_zstd() {
    Assert::instance(Zstd::class, Compression::negotiate(['unsupported', 'zstd'])->select(3));
  }

  #[Test, Runtime(extensions: ['zstd']), Values([[[], -1], [['zstdCompressionLevel' => 6], 6]])]
  public function negotiate_zstd_with($options, $level) {
    $compressor= Compression::negotiate(['zstd'], $options)->select(3);

    Assert::instance(Zstd::class, $compressor);
    Assert::equals($level, $compressor->level);
  }
}