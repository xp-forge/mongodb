<?php namespace com\mongodb\unittest;

use com\mongodb\{Collection, Document, Int64, ObjectId, Options, Session, Error};
use test\{Assert, Before, Expect, Test, Values};
use util\UUID;

class CollectionTest {
  use WireTesting;

  private $sessionId;

  /** @return iterable */
  private function documents() {
    yield [[]];
    yield [[['_id' => 'one', 'name' => 'A'], ['_id' => 'two', 'name' => 'B']]];
  }

  /**
   * Returns a new fixture
   *
   * @param  var... $messages
   * @return com.mongodb.Collection
   */
  private function newFixture(... $messages) {
    $responses= array_merge([$this->hello(self::$PRIMARY)], $messages);
    $protocol= $this->protocol([self::$PRIMARY => $responses], 'primary');
    return new Collection($protocol->connect(), 'testing', 'tests');
  }

  #[Before]
  public function sessionId() {
    $this->sessionId= new UUID('5f375bfe-af78-4af8-bb03-5d441a66a5fb');
  }

  #[Test]
  public function name() {
    Assert::equals('tests', $this->newFixture([])->name());
  }

  #[Test]
  public function namespace() {
    Assert::equals('testing.tests', $this->newFixture([])->namespace());
  }

  #[Test]
  public function run_command() {
    $result= $this->newFixture($this->ok(['text' => 'PONG']))->run('ping');

    Assert::false($result->isCursor());
    Assert::equals(['ok' => 1, 'text' => 'PONG'], $result->value());
  }

  #[Test]
  public function run_command_as_cursor() {
    $index= [
      'v'                  => 2,
      'key'                => ['_created' => 1],
      'name'               => '_created_1',
      'expireAfterSeconds' => 1800,
    ];
    $result= $this->newFixture($this->cursor([$index]))->run('listIndexes', []);

    Assert::true($result->isCursor());
    Assert::equals([new Document($index)], iterator_to_array($result->cursor()));
  }


  #[Test]
  public function insert_one() {
    $result= $this->newFixture($this->ok(['n' => 1]))->insert(
      new Document(['_id' => 'one', 'name' => 'Test'])
    );

    Assert::equals([1, 'one'], [$result->inserted(), $result->id()]);
  }

  #[Test]
  public function insert_many() {
    $result= $this->newFixture($this->ok(['n' => 2]))->insert([
      new Document(['_id' => 'one', 'name' => 'A']),
      new Document(['_id' => 'two', 'name' => 'B']),
    ]);

    Assert::equals([2, ['one', 'two']], [$result->inserted(), $result->ids()]);
  }

  #[Test]
  public function upsert_when_updating() {
    $result= $this->newFixture($this->ok(['n' => 1, 'nModified' => 1]))->upsert(
      ['_id' => 'one'],
      new Document(['_id' => 'one', 'name' => 'A'])
    );

    Assert::equals([1, 1, []], [$result->matched(), $result->modified(), $result->upserted()]);
  }

  #[Test]
  public function upsert_when_inserting() {
    $upsert= ['index' => 0, '_id' => new ObjectId('631c6206306c05628f1caff7')];
    $result= $this->newFixture($this->ok(['n' => 1, 'nModified' => 0, 'upserted' => [$upsert]]))->upsert(
      ['_id' => 'one'],
      new Document(['_id' => 'one', 'name' => 'A'])
    );

    Assert::equals([1, 0, [$upsert['_id']]], [$result->matched(), $result->modified(), $result->upserted()]);
  }

  #[Test]
  public function update_one() {
    $result= $this->newFixture($this->ok(['n' => 1, 'nModified' => 1]))->update(
      '6100',
      ['$inc' => ['qty' => 1]]
    );
    Assert::equals([1, 1], [$result->matched(), $result->modified()]);
  }

  #[Test]
  public function update_many() {
    $result= $this->newFixture($this->ok(['n' => 2, 'nModified' => 1]))->update(
      ['name' => 'Test'],
      ['$inc' => ['qty' => 1]]
    );

    Assert::equals([2, 1], [$result->matched(), $result->modified()]);
  }

  #[Test]
  public function delete_one() {
    $result= $this->newFixture($this->ok(['n' => 1]))->delete('6100');

    Assert::equals(1, $result->deleted());
  }

  #[Test]
  public function delete_many() {
    $result= $this->newFixture($this->ok(['n' => 2]))->delete(['name' => 'Test']);

    Assert::equals(2, $result->deleted());
  }

  #[Test]
  public function count_empty() {
    $collection= $this->newFixture($this->cursor([]));
    Assert::equals(0, $collection->count());
  }

  #[Test]
  public function count() {
    $collection= $this->newFixture($this->cursor([['n' => 1]]));
    Assert::equals(1, $collection->count());
  }

  #[Test]
  public function distinct() {
    $collection= $this->newFixture($this->cursor([['values' => ['A', 'B', 'C']]]));
    Assert::equals(['A', 'B', 'C'], $collection->distinct('name'));
  }

  #[Test, Values(from: 'documents')]
  public function find($documents) {
    $collection= $this->newFixture($this->cursor($documents));

    $results= [];
    foreach ($collection->find([]) as $document) {
      $results[]= $document->properties();
    }
    Assert::equals($documents, $results);
  }

  #[Test, Values(from: 'documents')]
  public function aggregate($documents) {
    $collection= $this->newFixture($this->cursor($documents));
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

  #[Test, Values(from: 'documents')]
  public function aggregate_using_empty_pipeline($documents) {
    $collection= $this->newFixture($this->cursor($documents));

    $results= [];
    foreach ($collection->aggregate([]) as $document) {
      $results[]= $document->properties();
    }
    Assert::equals($documents, $results);
  }

  #[Test, Values(from: 'documents')]
  public function watch($documents) {
    $collection= $this->newFixture($this->cursor($documents));

    $results= [];
    foreach ($collection->watch() as $document) {
      $results[]= $document->properties();
    }
    Assert::equals($documents, $results);
  }

  #[Test]
  public function string_representation() {
    Assert::equals(
      'com.mongodb.Collection<testing.tests@mongodb://'.self::$PRIMARY.'>',
      $this->newFixture([])->toString()
    );
  }

  #[Test]
  public function find_with_session() {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->cursor([]), $this->ok()]];
    $proto= $this->protocol($replies, 'primary')->connect();

    $session= new Session($proto, $this->sessionId);
    try {
      $coll= new Collection($proto, 'test', 'tests');
      $coll->find([], $session);
    } finally {
      $session->close();
    }

    $find= [
      'find'            => 'tests',
      'filter'          => (object)[],
      '$db'             => 'test',
      'lsid'            => ['id' => $this->sessionId],
      '$readPreference' => ['mode' => 'primary']
    ];
    Assert::equals($find, $proto->connections()[self::$PRIMARY]->command(-2));
  }

  #[Test]
  public function find_with_options() {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->cursor([])]];
    $proto= $this->protocol($replies, 'primary')->connect();

    $coll= new Collection($proto, 'test', 'tests');
    $coll->find([], new Options(['sort' => ['name' => -1]]));

    $find= [
      'find'            => 'tests',
      'filter'          => (object)[],
      '$db'             => 'test',
      'sort'            => ['name' => -1],
      '$readPreference' => ['mode' => 'primary']
    ];
    Assert::equals($find, $proto->connections()[self::$PRIMARY]->command(-1));
  }

  #[Test]
  public function find_with_read_preference() {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->cursor([])]];
    $proto= $this->protocol($replies, 'primary')->connect();

    $coll= new Collection($proto, 'test', 'tests');
    $coll->find([], (new Options())->readPreference('secondaryPreferred'));

    $find= [
      'find'            => 'tests',
      'filter'          => (object)[],
      '$db'             => 'test',
      '$readPreference' => ['mode' => 'secondaryPreferred']
    ];
    Assert::equals($find, $proto->connections()[self::$PRIMARY]->command(-1));
  }

  #[Test, Values(['local', 'majority'])]
  public function find_with_read_concern($level) {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->cursor([])]];
    $proto= $this->protocol($replies, 'primary')->connect();

    $coll= new Collection($proto, 'test', 'tests');
    $coll->find([], (new Options())->readConcern($level));

    $find= [
      'find'            => 'tests',
      'filter'          => (object)[],
      '$db'             => 'test',
      'readConcern'     => ['level' => $level],
      '$readPreference' => ['mode' => 'primary']
    ];
    Assert::equals($find, $proto->connections()[self::$PRIMARY]->command(-1));
  }

  #[Test, Values([1, 'majority'])]
  public function update_with_write_concern($w) {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->ok()]];
    $proto= $this->protocol($replies, 'primary')->connect();

    $coll= new Collection($proto, 'test', 'tests');
    $coll->update([], [], (new Options())->writeConcern($w));

    $find= [
      'update'          => 'tests',
      'updates'         => [['u' => [], 'q' => [], 'multi' => true]],
      'ordered'         => true,
      '$db'             => 'test',
      'writeConcern'    => ['w' => $w],
      '$readPreference' => ['mode' => 'primary']
    ];
    Assert::equals($find, $proto->connections()[self::$PRIMARY]->command(-1));
  }

  #[Test, Expect(class: Error::class, message: 'Test')]
  public function error_raised() {
    $fixture= $this->newFixture($this->error(6100, 'TestingError', 'Test'));
    $fixture->update('6100', ['$inc' => ['qty' => 1]]);
  }

  #[Test]
  public function not_writable_primary_retried_during_update() {
    $fixture= $this->newFixture(
      $this->error(10107, 'NotWritablePrimary'),
      $this->hello(self::$PRIMARY),
      $this->ok(['n' => 1, 'nModified' => 1])
    );
    $fixture->update('6100', ['$inc' => ['qty' => 1]]);
  }

  #[Test]
  public function not_writable_primary_retried_during_run() {
    $fixture= $this->newFixture(
      $this->error(10107, 'NotWritablePrimary'),
      $this->hello(self::$PRIMARY),
      $this->ok(['n' => 1, 'nModified' => 1])
    );

    $fixture->run('findAndModify', [
      'query'  => ['id' => '6100'],
      'update' => ['$inc' => ['qty' => 1]],
      'new'    => true,  // Return modified document
      'upsert' => true,
    ]);
  }

  #[Test, Expect(class: Error::class, message: 'Second occurrance')]
  public function not_writable_primary_not_retried_more_than_once() {
    $fixture= $this->newFixture(
      $this->error(10107, 'NotWritablePrimary', 'First occurrance'),
      $this->hello(self::$PRIMARY),
      $this->error(10107, 'NotWritablePrimary', 'Second occurrance')
    );
    $fixture->update('6100', ['$inc' => ['qty' => 1]]);
  }
}