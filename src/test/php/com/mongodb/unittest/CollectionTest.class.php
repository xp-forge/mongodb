<?php namespace com\mongodb\unittest;

use com\mongodb\{Protocol, Collection, Document};
use unittest\Assert;

class CollectionTest {
  private $protocol;

  #[@before]
  public function protocol() {
    $this->protocol= newinstance(Protocol::class, ['mongodb://test'], [
      'responses' => [],
      'returning' => function($response) { $this->responses[]= $response; return $this; },
      'connect'   => function() { /** NOOP */ },
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
    return new Collection($this->protocol->returning($response), 'testing', 'tests');
  }

  #[@test]
  public function insert_one() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 1])->insert(
      new Document(['_id' => 'one', 'name' => 'Test'])
    );

    Assert::equals([1, 'one'], [$result->inserted(), $result->id()]);
  }

  #[@test]
  public function insert_many() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 2])->insert([
      new Document(['_id' => 'one', 'name' => 'A']),
      new Document(['_id' => 'two', 'name' => 'B']),
    ]);

    Assert::equals([2, ['one', 'two']], [$result->inserted(), $result->ids()]);
  }

  #[@test]
  public function update_one() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 1, 'nModified' => 1])->update(
      '6100',
      ['$inc' => ['qty' => 1]]
    );
    Assert::equals([1, 1], [$result->matched(), $result->modified()]);
  }

  #[@test]
  public function update_many() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 2, 'nModified' => 1])->update(
      ['name' => 'Test'],
      ['$inc' => ['qty' => 1]]
    );

    Assert::equals([2, 1], [$result->matched(), $result->modified()]);
  }

  #[@test]
  public function delete_one() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 1])->delete('6100');

    Assert::equals(1, $result->deleted());
  }

  #[@test]
  public function delete_many() {
    $result= $this->newFixture(['ok' => 1.0, 'n' => 2])->delete(['name' => 'Test']);

    Assert::equals(2, $result->deleted());
  }
}