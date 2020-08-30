<?php namespace com\mongodb\unittest\result;

use com\mongodb\result\Cursor;
use com\mongodb\{Protocol, Document, Int64};
use unittest\Assert;

class CursorTest {
  private $proto;

  #[@before]
  public function protocol() {
    $this->proto= new Protocol('mongodb://test');
  }

  #[@test]
  public function can_create() {
    new Cursor($this->proto, [
      'firstBatch' => [],
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);
  }

  #[@test]
  public function namespace() {
    $fixture= new Cursor($this->proto, [
      'firstBatch' => [],
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);
    Assert::equals('test.collection', $fixture->namespace());
  }

  #[@test]
  public function documents() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor($this->proto, [
      'firstBatch' => $documents,
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);

    Assert::equals(
      array_map(function($d) { return new Document($d); }, $documents),
      iterator_to_array($fixture)
    );
  }

  #[@test]
  public function first_document() {
    $documents= [['_id' => 'one', 'qty'  => 1000]];
    $fixture= new Cursor($this->proto, [
      'firstBatch' => $documents,
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);

    Assert::equals(new Document($documents[0]), $fixture->first());
  }

  #[@test]
  public function first_when_not_found() {
    $documents= [];
    $fixture= new Cursor($this->proto, [
      'firstBatch' => [],
      'id'         => new Int64(0),
      'ns'         => 'test.collection'
    ]);

    Assert::null($fixture->first());
  }
}