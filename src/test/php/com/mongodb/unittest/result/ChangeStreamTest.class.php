<?php namespace com\mongodb\unittest\result;

use com\mongodb\io\Protocol;
use com\mongodb\result\ChangeStream;
use com\mongodb\{Document, Int64};
use test\{Assert, Before, Test};

class ChangeStreamTest {
  const RESUME = ['_data' => '826238BC7C000000182B...'];

  private $proto;

  #[Before]
  public function protocol() {
    $this->proto= new Protocol('mongodb://test');
  }

  #[Test]
  public function can_create() {
    new ChangeStream($this->proto, null, [
      'firstBatch'           => [],
      'id'                   => new Int64(0),
      'ns'                   => 'test.collection',
      'postBatchResumeToken' => self::RESUME
    ]);
  }

  #[Test]
  public function resumeToken() {
    $fixture= new ChangeStream($this->proto, null, [
      'firstBatch'           => [],
      'id'                   => new Int64(0),
      'ns'                   => 'test.collection',
      'postBatchResumeToken' => self::RESUME
    ]);
    Assert::equals(self::RESUME, $fixture->resumeToken());
  }
}