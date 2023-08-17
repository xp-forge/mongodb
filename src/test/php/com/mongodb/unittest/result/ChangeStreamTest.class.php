<?php namespace com\mongodb\unittest\result;

use com\mongodb\io\Commands;
use com\mongodb\result\ChangeStream;
use com\mongodb\{Document, Int64};
use lang\IllegalStateException;
use test\{Assert, Before, Test};

class ChangeStreamTest {
  const RESUME= ['_data' => '826238BC7C000000182B...'];

  private $commands;

  #[Before]
  public function commands() {
    $this->commands= new class() extends Commands {
      public function __construct() { }

      public function send($session, $sections) {
        throw new IllegalStateException('Not implemented');
      }
    };
  }

  #[Test]
  public function can_create() {
    new ChangeStream($this->commands, null, [
      'firstBatch'           => [],
      'id'                   => new Int64(0),
      'ns'                   => 'test.collection',
      'postBatchResumeToken' => self::RESUME
    ]);
  }

  #[Test]
  public function resumeToken() {
    $fixture= new ChangeStream($this->commands, null, [
      'firstBatch'           => [],
      'id'                   => new Int64(0),
      'ns'                   => 'test.collection',
      'postBatchResumeToken' => self::RESUME
    ]);
    Assert::equals(self::RESUME, $fixture->resumeToken());
  }
}