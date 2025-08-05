<?php namespace com\mongodb\unittest;

use com\mongodb\io\{Compression, Compressor};
use io\streams\compress\{None, Gzip};
use test\verify\Runtime;
use test\{Assert, Before, Test, Values};

class CompressionTest {
  private $compressor;

  #[Before]
  public function compressor() {
    $this->compressor= new Compressor(0, new None());
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
    Assert::instance(Gzip::class, Compression::negotiate(['unsupported', 'zlib'])->select(2)->algorithm);
  }

  #[Test, Runtime(extensions: ['zlib']), Values([[[], -1], [['zlibCompressionLevel' => 6], 6]])]
  public function negotiate_zlib_with($options, $level) {
    $compressor= Compression::negotiate(['zlib'], $options)->select(2);

    Assert::instance(Gzip::class, $compressor->algorithm);
    Assert::equals($level, $compressor->options);
  }

  #[Test, Runtime(extensions: ['zstd'])]
  public function negotiate_zstd() {
    Assert::instance(ZStandard::class, Compression::negotiate(['unsupported', 'zstd'])->select(3)->algorithm);
  }

  #[Test, Runtime(extensions: ['zstd']), Values([[[], -1], [['zstdCompressionLevel' => 6], 6]])]
  public function negotiate_zstd_with($options, $level) {
    $compressor= Compression::negotiate(['zstd'], $options)->select(3);

    Assert::instance(ZStandard::class, $compressor->algorithm);
    Assert::equals($level, $compressor->level);
  }
}