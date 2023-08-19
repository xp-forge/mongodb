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
}