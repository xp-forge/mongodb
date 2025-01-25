<?php namespace com\mongodb\unittest;

use com\mongodb\io\{DNS, Protocol};
use com\mongodb\{Error, ObjectId};
use io\IOException;
use lang\IllegalArgumentException;
use test\{Assert, Expect, Test, Values};
use util\{Bytes, Date};

class ProtocolTest {

  #[Test]
  public function can_create_with_connection_string() {
    new Protocol('mongodb://localhost');
  }

  #[Test]
  public function can_create_with_connections() {
    new Protocol([new TestingConnection('localhost', [])]);
  }

  #[Test, Values([['mongodb://localhost', ['params' => []]], ['mongodb://localhost?tls=true', ['params' => ['tls' => 'true']]], ['mongodb://u:p@localhost', ['user' => 'u', 'pass' => 'p', 'params' => []]], ['mongodb://u:p@localhost/admin', ['path' => '/admin', 'user' => 'u', 'pass' => 'p', 'params' => []]],])]
  public function options_via_connection_string($uri, $expected) {
    Assert::equals(
      ['scheme' => 'mongodb', 'nodes' => 'localhost'] + $expected,
      (new Protocol($uri))->options()
    );
  }

  #[Test]
  public function urlencoded_user() {
    Assert::equals('u:test', (new Protocol('mongodb://u%3atest:pass@localhost'))->options()['user']);
  }

  #[Test]
  public function urlencoded_pass() {
    Assert::equals('p:test', (new Protocol('mongodb://user:p%3atest@localhost'))->options()['pass']);
  }

  #[Test, Values(['mongodb://localhost', 'mongodb://localhost?tls=true', 'mongodb://u:p@localhost', 'mongodb://one.local,two.local:27018,[::1]:27019'])]
  public function dsn_via_connection_string($uri) {
    Assert::equals($uri, (new Protocol($uri))->dsn(true));
  }

  #[Test]
  public function dsn_without_password() {
    Assert::equals('mongodb://u:****@localhost', (new Protocol('mongodb://u:pass@localhost'))->dsn(false));
  }

  #[Test]
  public function cluster_via_connection_string() {
    $fixture= new Protocol('mongodb://one.local,two.local:27018,[::1]:27019');

    Assert::equals(
      ['one.local:27017', 'two.local:27018', '[::1]:27019'],
      array_keys($fixture->connections())
    );
  }

  #[Test]
  public function options_merged_with_connection_string() {
    $fixture= new Protocol('mongodb://localhost?authSource=test', [
      'user'   => 'test',
      'params' => ['tls' => 'true']
    ]);
    
    Assert::equals(
      ['scheme' => 'mongodb', 'user' => 'test', 'nodes' => 'localhost', 'params' => [
        'authSource' => 'test',
        'tls'        => 'true'
      ]],
      $fixture->options()
    );
  }

  #[Test, Expect(class: IllegalArgumentException::class, message: 'Unknown authentication mechanism UNKNOWN')]
  public function unknown_auth_mechanism() {
    new Protocol('mongodb://user:pass@localhost?authMechanism=UNKNOWN');
  }

  #[Test]
  public function resolve_dns_seed_list() {
    $p= new Protocol('mongodb+srv://example.com', [], null, new class() extends DNS {
      public function members(string $srv) { return ['mongo01.'.$srv => 27017, 'mongo02.'.$srv => 27317]; }
      public function params(string $srv) { return ['replicaSet=mySet']; }
    });

    Assert::equals(['mongo01.example.com:27017', 'mongo02.example.com:27317'], array_keys($p->connections()));
    Assert::equals(['replicaSet' => 'mySet', 'ssl' => 'true'], $p->options()['params']);
    Assert::equals('mongodb+srv://example.com?ssl=true&replicaSet=mySet', $p->dsn());
  }

  #[Test, Expect(class: Error::class, message: 'DNS lookup failed for example.com')]
  public function fails_when_dns_fails() {
    new Protocol('mongodb+srv://example.com', [], null, new class() extends DNS {
      public function members(string $srv) { throw new IOException('DNS server down'); }
      public function params(string $srv) { return []; }
    });
  }

  #[Test, Expect(class: Error::class, message: 'DNS does not contain MongoDB SRV records for example.com')]
  public function fails_when_dns_does_not_contain_seed_list() {
    new Protocol('mongodb+srv://example.com', [], null, new class() extends DNS {
      public function members(string $srv) { return []; }
      public function params(string $srv) { return []; }
    });
  }
}