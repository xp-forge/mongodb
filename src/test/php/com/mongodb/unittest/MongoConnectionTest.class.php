<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Collection, Database, Session, MongoConnection};
use lang\IllegalArgumentException;
use unittest\{Assert, Before, Expect, Test};

class MongoConnectionTest {
  const DSN = 'mongodb://test';

  private $protocol;

  #[Before]
  public function protocol() {
    $this->protocol= new class(self::DSN) extends Protocol {
      public $connected= null;
      public function connect() { $this->connected= true; }
      public function close() { $this->connected= false; }
      public function read($session, $sections) { }
      public function write($session, $sections) { }
    };
  }

  #[Test]
  public function can_create() {
    new MongoConnection(self::DSN);
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
    (new MongoConnection(self::DSN))->collection('not-a-namespace');
  }

  #[Test]
  public function connects_when_creating_session() {
    $fixture= new MongoConnection($this->protocol);
    $session= $fixture->session();

    Assert::instance(Session::class, $session);
    Assert::true($this->protocol->connected);
  }
}