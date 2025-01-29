<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Collection, Database, Document, Int64, MongoConnection, ObjectId, Session, Timestamp};
use lang\IllegalArgumentException;
use test\{Assert, Before, Expect, Test, Values};

class MongoConnectionTest {
  use WireTesting;

  const DSN = 'mongodb://test';

  #[Test]
  public function can_create_with_dsn() {
    new MongoConnection(self::DSN);
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function fails_with_empty_dsn() {
    new MongoConnection('');
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
  public function protocol_dsn() {
    Assert::equals(self::DSN, (new MongoConnection(self::DSN))->protocol()->dsn());
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

  #[Test, Values(['example.mongo.cosmos.azure.com:10255', 'example-germanywestcentral.mongo.cosmos.azure.com:10255'])]
  public function connect_to_azure_cosmos_db($resolved) {
    $protocol= $this->protocol(['example.mongo.cosmos.azure.com:10255' => [
      [
        'responseFlags'  => 0,
        'cursorID'       => 0,
        'startingFrom'   => 0,
        'numberReturned' => 1,
        'documents'      => [[
          'ok'       => 0,
          'code'     => 115,
          'codeName' => 'CommandNotSupported',
          'errmsg'   => 'Command Hello not supported prior to authentication',
        ]],
      ],
      [
        'responseFlags'  => 0,
        'cursorID'       => 0,
        'startingFrom'   => 0,
        'numberReturned' => 1,
        'documents'      => [[
          'ismaster'                     => true,
          'maxBsonObjectSize'            => 16777216,
          'maxMessageSizeBytes'          => 48000000,
          'maxWriteBatchSize'            => 1000,
          'localTime'                    => new Int64(1738183466672),
          'logicalSessionTimeoutMinutes' => 30,
          'minWireVersion'               => 0,
          'maxWireVersion'               => 18,
          'readOnly'                     => false,
          'tags'                         => ['region' => 'Germany West Central'],
          'hosts'                        => [$resolved],
          'setName'                      => 'globaldb',
          'setVersion'                   => 1,
          'primary'                      => $resolved,
          'me'                           => $resolved,
          'connectionId'                 => 382867193,
          'ok'                           => 1.0
        ]]
      ],
    ]]);
    $fixture= new MongoConnection($protocol);
    $fixture->connect();

    Assert::true($protocol->connections()[$resolved]->connected());
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
  public function selecting_database() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $database= (new MongoConnection($protocol))->database('test');

    Assert::instance(Database::class, $database);
    Assert::false($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function selecting_collection_via_namespace() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $collection= (new MongoConnection($protocol))->collection('test.products');

    Assert::instance(Collection::class, $collection);
    Assert::false($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test]
  public function selecting_collection_via_args() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $collection= (new MongoConnection($protocol))->collection('test', 'products');

    Assert::instance(Collection::class, $collection);
    Assert::false($protocol->connections()[self::$PRIMARY]->connected());
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function throws_when_given_non_namespace_name() {
    (new MongoConnection(self::DSN))->collection('not-a-namespace');
  }

  #[Test]
  public function creating_session() {
    $protocol= $this->protocol([$this->hello(self::$PRIMARY)]);
    $session= (new MongoConnection($protocol))->session();

    Assert::instance(Session::class, $session);
    Assert::false($protocol->connections()[self::$PRIMARY]->connected());
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