<?php namespace com\mongodb\unittest;

use com\mongodb\Options;
use com\mongodb\io\Protocol;
use test\{Assert, Before, Test, Values};

class OptionsTest {
  private $protocol;

  #[Before]
  public function protocol() {
    $this->protocol= new Protocol([]);
  }

  #[Test]
  public function send_when_created_without_arguments() {
    Assert::equals([], (new Options())->send($this->protocol));
  }

  #[Test, Values([[[]], [['skip' => 1]], [['skip' => 1, 'limit' => 1]]])]
  public function send($pairs) {
    Assert::equals($pairs, (new Options($pairs))->send($this->protocol));
  }

  #[Test]
  public function read_preference() {
    Assert::equals(
      ['$readPreference' => ['mode' => 'secondary']], 
      (new Options())->readPreference('secondary')->send($this->protocol)
    );
  }

  #[Test, Values(['local', 'available'])]
  public function read_concern($level) {
    Assert::equals(
      ['readConcern' => ['level' => $level]],
      (new Options())->readConcern($level)->send($this->protocol)
    );
  }

  #[Test, Values(['majority', 1])]
  public function write_concern($w) {
    Assert::equals(
      ['writeConcern' => ['w' => $w]],
      (new Options())->writeConcern($w)->send($this->protocol)
    );
  }

  #[Test]
  public function write_concern_with_timeout() {
    Assert::equals(
      ['writeConcern' => ['w' => 2, 'wtimeout' => 10000]],
      (new Options())->writeConcern(2, 10000)->send($this->protocol)
    );
  }

  #[Test]
  public function write_concern_with_journal() {
    Assert::equals(
      ['writeConcern' => ['w' => 2, 'j' => true]],
      (new Options())->writeConcern(2, null, true)->send($this->protocol)
    );
  }
}