<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Collection, Database, Session, MongoConnection, ObjectId, Timestamp, Int64, Document};
use lang\IllegalArgumentException;
use unittest\{Assert, Before, Expect, Test};

class MongoConnectionTest {
  use WireTesting;

  const DSN = 'mongodb://test';

  #[Test]
  public function can_create_with_dsn() {
    new MongoConnection(self::DSN);
  }

  #[Test]
  public function can_create_with_protocol() {
    new MongoConnection($this->protocol([]));
  }

  #[Test]
  public function string_representation() {
    Assert::equals(
      'com.mongodb.MongoConnection(mongodb://test)@null',
      (new MongoConnection(self::DSN))->toString()
    );
  }

  #[Test]
  public function two_connections_are_not_equal() {
    $one= new MongoConnection(self::DSN);
    $two= new MongoConnection(self::DSN);

    Assert::notEquals($one->hashCode(), $two->hashCode());
    Assert::equals(1, $one->compareTo($two));
  }

  #[Test]
  public function initially_not_connected() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    new MongoConnection($protocol);

    Assert::false($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function connect() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $fixture= new MongoConnection($protocol);
    $fixture->connect();

    Assert::true($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function close() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $fixture= new MongoConnection($protocol);
    $fixture->connect();
    $fixture->close();

    Assert::false($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function close_unconnected() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $fixture= new MongoConnection($protocol);
    $fixture->close();

    Assert::false($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function connects_when_selecting_database() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $database= (new MongoConnection($protocol))->database('test');

    Assert::instance(Database::class, $database);
    Assert::true($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function connects_when_selecting_collection_via_namespace() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $collection= (new MongoConnection($protocol))->collection('test.products');

    Assert::instance(Collection::class, $collection);
    Assert::true($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function connects_when_selecting_collection_via_args() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $collection= (new MongoConnection($protocol))->collection('test', 'products');

    Assert::instance(Collection::class, $collection);
    Assert::true($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function throws_when_given_non_namespace_name() {
    (new MongoConnection(self::DSN))->collection('not-a-namespace');
  }

  #[Test]
  public function connects_when_creating_session() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $session= (new MongoConnection($protocol))->session();

    Assert::instance(Session::class, $session);
    Assert::true($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test, Values([[null, []], ['test', ['filter' => ['name' => 'test']]], [['name' => 'test'], ['filter' => ['name' => 'test']]]])]
  public function databases_command($filter, $request) {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY), [
      'flags' => 0,
      'body'  => ['ok' => 1, 'databases' => []]
    ]]);

    iterator_count((new MongoConnection($protocol))->databases($filter));

    Assert::equals(
      ['listDatabases' => 1, '$db' => 'admin', '$readPreference' => ['mode' => 'primary']] + $request,
      $protocol->connections()[self::$PRIMARY]->command(-1)
    );
  }

  #[Test, Values(eval: '[6100, new Int64(6100)]')]
  public function database_enumeration($size) {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY), [
      'flags' => 0,
      'body'  => ['ok' => 1, 'databases' => [
        ['name' => 'test', 'sizeOnDisk' => $size, 'empty' => false]
      ]]
    ]]);

    Assert::equals(
      ['test' => ['name' => 'test', 'sizeOnDisk' => new Int64(6100), 'empty' => false, 'shards' => null]],
      iterator_to_array((new MongoConnection($protocol))->databases())
    );
  }

  #[Test]
  public function watch_command() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY), $this->cursor([])])->connect();
    (new MongoConnection($protocol))->watch();

    Assert::equals(
      [
        'aggregate'       => 1,
        'pipeline'        => [['$changeStream' => ['allChangesForCluster' => true]]],
        'cursor'          => (object)[],
        '$db'             => 'admin',
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
    $fixture= new MongoConnection($this->protocol([$this->hello(self::$PRIMARY), $this->cursor([$change])]));

    Assert::equals([new Document($change)], iterator_to_array($fixture->watch()));
  }
}