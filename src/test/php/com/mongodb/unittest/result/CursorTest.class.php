<?php namespace com\mongodb\unittest\result;

use com\mongodb\io\Protocol;
use com\mongodb\result\Cursor;
use com\mongodb\{Document, Int64};
use unittest\{Assert, Before, Test};

class CursorTest {
  private $proto;

  #[Before]
  public function protocol() {
    $this->proto= new Protocol('mongodb://test');
  }

  #[Test]
  public function can_create() {
    new Cursor($this->proto, null, [
      'firstBatch' => [],
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);
  }

  #[Test]
  public function namespace() {
    $fixture= new Cursor($this->proto, null, [
      'firstBatch' => [],
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);
    Assert::equals('test.collection', $fixture->namespace());
  }

  #[Test]
  public function documents() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor($this->proto, null, [
      'firstBatch' => $documents,
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);

    Assert::equals(
      array_map(function($d) { return new Document($d); }, $documents),
      iterator_to_array($fixture)
    );
  }

  #[Test]
  public function first_document() {
    $documents= [['_id' => 'one', 'qty'  => 1000]];
    $fixture= new Cursor($this->proto, null, [
      'firstBatch' => $documents,
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);

    Assert::equals(new Document($documents[0]), $fixture->first());
  }

  #[Test]
  public function first_when_not_found() {
    $documents= [];
    $fixture= new Cursor($this->proto, null, [
      'firstBatch' => [],
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);

    Assert::null($fixture->first());
  }
}