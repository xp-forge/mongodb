<?php namespace com\mongodb\unittest\result;

use com\mongodb\io\Protocol;
use com\mongodb\result\Cursor;
use com\mongodb\{Document, Int64};
use unittest\{Assert, Before, Test};

class CursorTest {
  private $proto;

  #[Before]
  public function protocol() {
    $this->proto= new class('mongodb://test') extends Protocol {
      private $return= null;
      public function returning($return) { $this->return= $return; return $this; }
      public function read($session, $sections) { return ['flags' => 0, 'body' => array_shift($this->return)]; }
    };
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

  #[Test]
  public function iterate_all_given_next_batches() {
    $documents= [
      ['_id' => 'eins', 'qty'  => 111],
      ['_id' => 'zwei', 'qty'  => 222],
      ['_id' => 'drei', 'qty'  => 333],
      ['_id' => 'vier', 'qty'  => 444],
      ['_id' => 'fünf', 'qty'  => 555],
    ];

    $firstBatch= [
      'firstBatch' => [$documents[0], $documents[1]],
      'id'         => new Int64(1),
      'ns'         => 'test.collection',
    ];
    $nextBatches= [
      ['cursor' => [
        'nextBatch'  => [$documents[2], $documents[3]],
        'id'         => new Int64(1),
        'ns'         => 'test.collection',
      ]],
      ['cursor' => [
        'nextBatch'  => [$documents[4]],
        'id'         => new Int64(0),
        'ns'         => 'test.collection',
      ]],
    ];

    Assert::equals(
      array_map(function($d) { return new Document($d); }, $documents),
      iterator_to_array(new Cursor($this->proto->returning($nextBatches), null, $firstBatch))
    );
  }

  #[Test]
  public function close_before_iterating_all() {
    $firstBatch= [
      'firstBatch' => [],
      'id'         => new Int64(1),
      'ns'         => 'test.collection',
    ];
    (new Cursor($this->proto->returning(['ok' => 1]), null, $firstBatch))->close();
  }
}