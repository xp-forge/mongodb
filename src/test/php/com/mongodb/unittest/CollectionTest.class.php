<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Collection, Document, Int64};
use unittest\{Assert, Before, Test};

class CollectionTest {
  private $protocol;

  /** @return iterable */
  private function documents() {
    yield [[]];
    yield [[['_id' => 'one', 'name' => 'A'], ['_id' => 'two', 'name' => 'B']]];
  }

  #[Before]
  public function protocol() {
    $this->protocol= newinstance(Protocol::class, ['mongodb://test'], [
      'responses' => [],
      'returning' => function($response) { $this->responses[]= $response; return $this; },
      'connect'   => function() { },
      'close'     => function() { /** NOOP */ },
      'read'      => function($session, $sections) { return ['body' => array_shift($this->responses)]; },
      'write'     => function($session, $sections) { return ['body' => array_shift($this->responses)]; },
    ]);
  }

  /**
   * Returns a new fixture
   *
   * @param  [:var] $response
   * @return com.mongodb.Collection
   */
  private function newFixture($response) {
    $this->protocol->connect();
    return new Collection($this->protocol->returning($response), 'testing', 'tests');
  }

  #[Test]
  public function insert_one() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 1])->insert(
      new Document(['_id' => 'one', 'name' => 'Test'])
    );

    Assert::equals([1, 'one'], [$result->inserted(), $result->id()]);
  }

  #[Test]
  public function insert_many() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 2])->insert([
      new Document(['_id' => 'one', 'name' => 'A']),
      new Document(['_id' => 'two', 'name' => 'B']),
    ]);

    Assert::equals([2, ['one', 'two']], [$result->inserted(), $result->ids()]);
  }

  #[Test]
  public function update_one() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 1, 'nModified' => 1])->update(
      '6100',
      ['$inc' => ['qty' => 1]]
    );
    Assert::equals([1, 1], [$result->matched(), $result->modified()]);
  }

  #[Test]
  public function update_many() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 2, 'nModified' => 1])->update(
      ['name' => 'Test'],
      ['$inc' => ['qty' => 1]]
    );

    Assert::equals([2, 1], [$result->matched(), $result->modified()]);
  }

  #[Test]
  public function delete_one() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 1])->delete('6100');

    Assert::equals(1, $result->deleted());
  }

  #[Test]
  public function delete_many() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 2])->delete(['name' => 'Test']);

    Assert::equals(2, $result->deleted());
  }

  #[Test]
  public function count_empty() {
    $collection= $this->newFixture(['ok' => 1.0, 'cursor' => [
      'firstBatch' => [],
      'id'         => new Int64(0),
      'ns'         => 'testing.tests'
    ]]);

    Assert::equals(0, $collection->count());
  }

  #[Test]
  public function count() {
    $collection= $this->newFixture(['ok' => 1.0, 'cursor' => [
      'firstBatch' => [['n' => 1]],
      'id'         => new Int64(0),
      'ns'         => 'testing.tests'
    ]]);

    Assert::equals(1, $collection->count());
  }

  #[Test, Values('documents')]
  public function aggregate($documents) {
    $collection= $this->newFixture(['ok' => 1.0, 'cursor' => [
      'firstBatch' => $documents,
      'id'         => new Int64(0),
      'ns'         => 'testing.tests'
    ]]);
    $pipeline= [
      ['$match'   => ['item' => ['$eq' => 'Test']]],
      ['$project' => ['item' => true]],
    ];

    $results= [];
    foreach ($collection->aggregate($pipeline) as $document) {
      $results[]= $document->properties();
    }
    Assert::equals($documents, $results);
  }

  #[Test, Values('documents')]
  public function aggregate_using_empty_pipeline($documents) {
    $collection= $this->newFixture(['ok' => 1.0, 'cursor' => [
      'firstBatch' => $documents,
      'id'         => new Int64(0),
      'ns'         => 'testing.tests'
    ]]);

    $results= [];
    foreach ($collection->aggregate([]) as $document) {
      $results[]= $document->properties();
    }
    Assert::equals($documents, $results);
  }
}