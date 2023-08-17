<?php namespace com\mongodb\unittest\result;

use com\mongodb\io\Commands;
use com\mongodb\result\Cursor;
use com\mongodb\{Document, Int64};
use lang\IllegalStateException;
use test\{Assert, Expect, Before, Test};

class CursorTest {
  private $commands;

  /**
   * Creates first batch
   *
   * @param  [:var][] $documents
   * @param  bool $last
   * @return [:var]
   */
  private function firstBatch($documents= [], $last= true) {
    return [
      'firstBatch' => $documents,
      'id'         => new Int64($last ? 0 : 1),
      'ns'         => 'test.collection'
    ];
  }

  /**
   * Creates next batch
   *
   * @param  [:var][] $documents
   * @param  bool $last
   * @return [:var]
   */
  private function nextBatch($documents= [], $last= true) {
    return ['cursor' => [
      'nextBatch'  => $documents,
      'id'         => new Int64($last ? 0 : 1),
      'ns'         => 'test.collection'
    ]];
  }

  #[Before]
  public function commands() {
    $this->commands= new class() extends Commands {
      private $return= [];

      public function __construct() { }

      public function returning($return) {
        $this->return= $return;
        return $this;
      }

      public function send($session, $sections) {
        return ['flags' => 0, 'body' => array_shift($this->return)];
      }
    };
  }

  #[Test]
  public function can_create() {
    new Cursor($this->commands, null, $this->firstBatch());
  }

  #[Test]
  public function namespace() {
    $fixture= new Cursor($this->commands, null, $this->firstBatch());
    Assert::equals('test.collection', $fixture->namespace());
  }

  #[Test]
  public function documents() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor($this->commands, null, $this->firstBatch($documents));

    Assert::equals(
      array_map(function($d) { return new Document($d); }, $documents),
      iterator_to_array($fixture)
    );
  }

  #[Test]
  public function first_document() {
    $documents= [['_id' => 'one', 'qty'  => 1000]];
    $fixture= new Cursor($this->commands, null, $this->firstBatch($documents));

    Assert::equals(new Document($documents[0]), $fixture->first());
  }

  #[Test]
  public function first_when_not_found() {
    $documents= [];
    $fixture= new Cursor($this->commands, null, $this->firstBatch());

    Assert::null($fixture->first());
  }

  #[Test, Expect(class: IllegalStateException::class, message: '/Cursor has been forwarded/')]
  public function first_after_iterating() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor(
      $this->commands->returning([$this->nextBatch([$documents[1]], true)]),
      null,
      $this->firstBatch([$documents[0]], false)
    );
    iterator_count($fixture);

    $fixture->first();
  }

  #[Test]
  public function all_documents() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor($this->commands, null, $this->firstBatch($documents));

    Assert::equals(
      array_map(function($d) { return new Document($d); }, $documents),
      $fixture->all()
    );
  }

  #[Test]
  public function all_when_not_found() {
    $documents= [];
    $fixture= new Cursor($this->commands, null, $this->firstBatch());

    Assert::equals([], $fixture->all());
  }

  #[Test, Expect(class: IllegalStateException::class, message: '/Cursor has been forwarded/')]
  public function all_after_iterating() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor(
      $this->commands->returning([$this->nextBatch([$documents[1]], true)]),
      null,
      $this->firstBatch([$documents[0]], false)
    );
    iterator_count($fixture);

    $fixture->all();
  }

  #[Test]
  public function present() {
    $documents= [['_id' => 'one', 'qty'  => 1000]];
    $fixture= new Cursor($this->commands, null, $this->firstBatch($documents));

    Assert::true($fixture->present());
  }

  #[Test]
  public function present_when_not_found() {
    $documents= [];
    $fixture= new Cursor($this->commands, null, $this->firstBatch());

    Assert::false($fixture->present());
  }

  #[Test]
  public function present_after_iterating() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor($this->commands, null, $this->firstBatch($documents));
    iterator_count($fixture);

    Assert::true($fixture->present());
  }

  #[Test]
  public function present_after_iterating_batches() {
    $documents= [['_id' => 'one', 'qty'  => 1000], ['_id' => 'two', 'qty'  => 6100]];
    $fixture= new Cursor(
      $this->commands->returning([$this->nextBatch([$documents[1]], true)]),
      null,
      $this->firstBatch([$documents[0]], false)
    );
    iterator_count($fixture);

    Assert::true($fixture->present());
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

    $firstBatch= $this->firstBatch([$documents[0], $documents[1]], false);
    $nextBatches= [
      $this->nextBatch([$documents[2], $documents[3]], false),
      $this->nextBatch([$documents[4]], true),
    ];

    Assert::equals(
      array_map(function($d) { return new Document($d); }, $documents),
      iterator_to_array(new Cursor($this->commands->returning($nextBatches), null, $firstBatch))
    );
  }

  #[Test]
  public function close_before_iterating_all() {
    (new Cursor($this->commands->returning(['ok' => 1]), null, $this->firstBatch([], false)))->close();
  }

  #[Test]
  public function string_representation() {
    $fixture= new Cursor($this->commands, null, $this->firstBatch());
    Assert::equals(
      'com.mongodb.result.Cursor(id= 0, ns= test.collection, current= firstBatch, size= 0)',
      $fixture->toString()
    );
  }

  #[Test]
  public function same_cursors_qual() {
    $one= new Cursor($this->commands, null, $this->firstBatch([]));
    $two= new Cursor($this->commands, null, $this->firstBatch([]));

    Assert::equals($one, $two);
    Assert::equals($one->hashCode(), $two->hashCode());
  }

  #[Test]
  public function different_cursors_not_equal() {
    $one= new Cursor($this->commands, null, $this->firstBatch([['_id' => 'one', 'qty'  => 1000]]));
    $two= new Cursor($this->commands, null, $this->firstBatch([]));

    Assert::notEquals($one, $two);
    Assert::notEquals($one->hashCode(), $two->hashCode());
  }
}