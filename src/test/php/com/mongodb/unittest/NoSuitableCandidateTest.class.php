<?php namespace com\mongodb\unittest;

use com\mongodb\NoSuitableCandidate;
use test\{Assert, Before, Test};

class NoSuitableCandidateTest {
  private $args;

  #[Before]
  public function args() {
    $this->args= ['writing', ['primary:27017', 'secondary:27017']];
  }

  #[Test]
  public function can_create() {
    new NoSuitableCandidate(...$this->args);
  }

  #[Test]
  public function candidates() {
    Assert::equals(['primary:27017', 'secondary:27017'], (new NoSuitableCandidate(...$this->args))->candidates());
  }

  #[Test]
  public function compoundMessage() {
    Assert::equals(
      'Exception com.mongodb.NoSuitableCandidate (#6:HostUnreachable "No suitable candidate eligible for writing, tried primary:27017, secondary:27017")',
      (new NoSuitableCandidate(...$this->args))->compoundMessage()
    );
  }
}