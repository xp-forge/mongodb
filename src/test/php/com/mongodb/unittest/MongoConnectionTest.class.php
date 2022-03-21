<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Collection, Database, Session, MongoConnection};
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
}