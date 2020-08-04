<?php namespace com\mongodb\unittest;

use com\mongodb\{MongoConnection, Protocol, Database, Collection};
use lang\IllegalArgumentException;
use unittest\Assert;

class MongoConnectionTest {
  const CONNECTION_STRING = 'mongodb://test';

  private $protocol;

  #[@before]
  public function protocol() {
    $this->protocol= newinstance(Protocol::class, [self::CONNECTION_STRING], [
      'connected' => null,
      'connect'   => function() { $this->connected= true; },
      'close'     => function() { $this->connected= false; },
    ]);
  }

  #[@test]
  public function can_create() {
    new MongoConnection(self::CONNECTION_STRING);
  }

  #[@test]
  public function initially_not_connected() {
    $fixture= new MongoConnection($this->protocol);

    Assert::null($this->protocol->connected);
  }

  #[@test]
  public function connect_returns_self() {
    $fixture= new MongoConnection($this->protocol);
    $return= $fixture->connect();

    Assert::equals($fixture, $return);
    Assert::true($this->protocol->connected);
  }

  #[@test]
  public function close() {
    $fixture= new MongoConnection($this->protocol);
    $fixture->connect()->close();

    Assert::false($this->protocol->connected);
  }

  #[@test]
  public function connects_when_selecting_database() {
    $fixture= new MongoConnection($this->protocol);
    $database= $fixture->database('test');

    Assert::instance(Database::class, $database);
    Assert::true($this->protocol->connected);
  }

  #[@test]
  public function connects_when_selecting_collection_via_namespace() {
    $fixture= new MongoConnection($this->protocol);
    $collection= $fixture->collection('test.products');

    Assert::instance(Collection::class, $collection);
    Assert::true($this->protocol->connected);
  }

  #[@test]
  public function connects_when_selecting_collection_via_args() {
    $fixture= new MongoConnection($this->protocol);
    $collection= $fixture->collection('test', 'products');

    Assert::instance(Collection::class, $collection);
    Assert::true($this->protocol->connected);
  }

  #[@test, @expect(IllegalArgumentException::class)]
  public function throws_when_given_non_namespace_name() {
    (new MongoConnection(self::CONNECTION_STRING))->collection('not-a-namespace');
  }
}