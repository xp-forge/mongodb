<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Collection, Database, MongoConnection};
use lang\IllegalArgumentException;
use unittest\{Assert, Before, Expect, Test};

class MongoConnectionTest {
  const CONNECTION_STRING = 'mongodb://test';

  private $protocol;

  #[Before]
  public function protocol() {
    $this->protocol= new class(self::CONNECTION_STRING) extends Protocol {
      public $connected= null;
      public function connect() { $this->connected= true; }
      public function close() { $this->connected= false; }
    };
  }

  #[Test]
  public function can_create() {
    new MongoConnection(self::CONNECTION_STRING);
  }

  #[Test]
  public function initially_not_connected() {
    $fixture= new MongoConnection($this->protocol);

    Assert::null($this->protocol->connected);
  }

  #[Test]
  public function connect_returns_self() {
    $fixture= new MongoConnection($this->protocol);
    $return= $fixture->connect();

    Assert::equals($fixture, $return);
    Assert::true($this->protocol->connected);
  }

  #[Test]
  public function close() {
    $fixture= new MongoConnection($this->protocol);
    $fixture->connect()->close();

    Assert::false($this->protocol->connected);
  }

  #[Test]
  public function connects_when_selecting_database() {
    $fixture= new MongoConnection($this->protocol);
    $database= $fixture->database('test');

    Assert::instance(Database::class, $database);
    Assert::true($this->protocol->connected);
  }

  #[Test]
  public function connects_when_selecting_collection_via_namespace() {
    $fixture= new MongoConnection($this->protocol);
    $collection= $fixture->collection('test.products');

    Assert::instance(Collection::class, $collection);
    Assert::true($this->protocol->connected);
  }

  #[Test]
  public function connects_when_selecting_collection_via_args() {
    $fixture= new MongoConnection($this->protocol);
    $collection= $fixture->collection('test', 'products');

    Assert::instance(Collection::class, $collection);
    Assert::true($this->protocol->connected);
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function throws_when_given_non_namespace_name() {
    (new MongoConnection(self::CONNECTION_STRING))->collection('not-a-namespace');
  }
}