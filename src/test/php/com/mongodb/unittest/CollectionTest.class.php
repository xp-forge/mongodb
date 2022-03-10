<?php namespace com\mongodb\unittest;

use com\mongodb\{Collection, Document, Protocol};
use unittest\{Assert, Before, Test};

class CollectionTest {
  private $protocol;

  #[Before]
  public function protocol() {
    $this->protocol= newinstance(Protocol::class, ['mongodb://test'], [
      'responses' => [],
      'returning' => function($response) { $this->responses[]= $response; return $this; },
      'connect'   => function() { $this->server= ['$kind' => null]; },
      'close'     => function() { /** NOOP */ },
      'send'      => function($op, $payload) { return ['flags' => 0, 'body' => array_shift($this->responses)]; }
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
}