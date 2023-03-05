<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Collection, Database, Document, ObjectId, Timestamp};
use test\{Assert, Test};
use util\UUID;

class DatabaseTest {
  use WireTesting;

  #[Test]
  public function can_create() {
    new Database($this->protocol([]), 'test');
  }

  #[Test]
  public function name() {
    Assert::equals('test', (new Database($this->protocol([]), 'test'))->name());
  }

  #[Test]
  public function collection() {
    $protocol= $this->protocol([]);

    Assert::equals(
      new Collection($protocol, 'test', 'entries'),
      (new Database($protocol, 'test'))->collection('entries')
    );
  }

  #[Test]
  public function collections_command() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY), $this->cursor([])])->connect();
    (new Database($protocol, 'test'))->collections();

    Assert::equals(
      [
        'listCollections' => (object)[],
        '$db'             => 'test',
        '$readPreference' => ['mode' => 'primary']
      ],
      $protocol->connections()[self::$PRIMARY]->command(-1)
    );
  }

  #[Test]
  public function empty_collections() {
    $fixture= new Database(
      $this->protocol([$this->hello(self::$PRIMARY), $this->cursor([])])->connect(),
      'test'
    );

    Assert::equals([], iterator_to_array($fixture->collections()));
  }

  #[Test]
  public function collections() {
    $collection= [
      'name'    => 'products',
      'type'    => 'collection',
      'options' => (object)[],
      'info'    => ['readOnly' => false, 'uuid' => new UUID('5f375bfe-af78-4af8-bb03-5d441a66a5fb')],
      'idIndex' => ['v' => 2, 'key' => ['_id' => 1], 'name' => '_id_', 'ns' => 'test.products'],
    ];
    $fixture= new Database(
      $this->protocol([$this->hello(self::$PRIMARY), $this->cursor([$collection])])->connect(),
      'test'
    );

    Assert::equals([new Document($collection)], iterator_to_array($fixture->collections()));
  }

  #[Test]
  public function watch_command() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY), $this->cursor([])])->connect();
    (new Database($protocol, 'test'))->watch();

    Assert::equals(
      [
        'aggregate'       => 1,
        'pipeline'        => [['$changeStream' => (object)[]]],
        'cursor'          => (object)[],
        '$db'             => 'test',
        '$readPreference' => ['mode' => 'primary']
      ],
      $protocol->connections()[self::$PRIMARY]->command(-1)
    );
  }

  #[Test]
  public function watch() {
    $change= [
      '_id'           => ['_data' => '826238BC7C000000182B...'],
      'operationType' => 'delete',
      'clusterTime'   => new Timestamp(1647885436, 24),
      'ns'            => ['db' => 'test', 'coll' => 'products'],
      'documentKey'   => ['_id' => new ObjectId('622b6377414115a6034c47e2')],
    ];
    $fixture= new Database(
      $this->protocol([$this->hello(self::$PRIMARY), $this->cursor([$change])])->connect(),
      'test'
    );

    Assert::equals([new Document($change)], iterator_to_array($fixture->watch()));
  }

  #[Test]
  public function string_representation() {
    Assert::equals(
      'com.mongodb.Database<test@mongodb://'.self::$PRIMARY.'>',
      (new Database($this->protocol([$this->hello(self::$PRIMARY)]), 'test'))->toString()
    );
  }
}