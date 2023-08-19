<?php namespace com\mongodb\unittest;

use com\mongodb\io\Protocol;
use com\mongodb\{Error, Session};
use lang\IllegalStateException;
use test\{Assert, Before, Expect, Test};
use util\UUID;

class SessionTest {
  const ID = '5f375bfe-af78-4af8-bb03-5d441a66a5fb';

  private $protocol;

  #[Before]
  public function protocol() {
    $this->protocol= new class('mongo://localhost') extends Protocol {
      public $sent= [];
      public function write(array $options, $sections) { return ['ok' => 1]; }
    };
  }

  #[Test]
  public function can_create_with_uuid() {
    new Session($this->protocol, new UUID(self::ID));
  }

  #[Test]
  public function can_create_with_string() {
    new Session($this->protocol, self::ID);
  }

  #[Test]
  public function id() {
    Assert::equals(new UUID(self::ID), (new Session($this->protocol, self::ID))->id());
  }

  #[Test]
  public function hash_of() {
    Assert::equals('S'.self::ID, (new Session($this->protocol, self::ID))->hashCode());
  }

  #[Test]
  public function string_representation() {
    Assert::equals(
      'com.mongodb.Session(id: '.self::ID.')',
      (new Session($this->protocol, self::ID))->toString()
    );
  }

  #[Test]
  public function string_representation_with_transaction() {
    Assert::equals(
      'com.mongodb.Session(id: '.self::ID.", transaction: [\n".
      "  txnNumber => com.mongodb.Int64(1)\n".
      "  autocommit => false\n".
      "  startTransaction => true\n".
      "])",
      (new Session($this->protocol, self::ID))->transaction()->toString()
    );
  }

  #[Test]
  public function comparison() {
    $fixture= new Session($this->protocol, self::ID);

    Assert::equals(new Session($this->protocol, self::ID), $fixture);
    Assert::notEquals(new Session($this->protocol, '6f375bfe-af78-4af8-bb03-5d441a66a5fb'), $fixture);
  }

  #[Test]
  public function initially_not_closed() {
    Assert::false((new Session($this->protocol, self::ID))->closed());
  }

  #[Test]
  public function close() {
    $session= new Session($this->protocol, self::ID);
    $session->close();

    Assert::true($session->closed());
  }

  #[Test]
  public function closes_even_when_endSessions_raises_an_exception() {
    $protocol= new class('mongo://localhost') extends Protocol {
      public function write(array $options, $sections) {
        throw new Error(6100, 'MorePower', 'Closing failed');
      }
    };
    $session= new Session($protocol, self::ID);
    $session->close();

    Assert::true($session->closed());
  }

  #[Test]
  public function commit_without_any_commands() {
    (new Session($this->protocol, self::ID))->transaction()->commit();
  }

  #[Test]
  public function abort_without_any_commands() {
    (new Session($this->protocol, self::ID))->transaction()->abort();
  }

  #[Test, Expect(IllegalStateException::class)]
  public function nested_transaction() {
    (new Session($this->protocol, self::ID))->transaction()->transaction();
  }

  #[Test, Expect(IllegalStateException::class)]
  public function commit_without_transaction() {
    (new Session($this->protocol, self::ID))->commit();
  }

  #[Test, Expect(IllegalStateException::class)]
  public function abort_without_transaction() {
    (new Session($this->protocol, self::ID))->abort();
  }

  #[Test, Expect(IllegalStateException::class)]
  public function send_with_different_protocol() {
    (new Session($this->protocol, self::ID))->send(new Protocol('mongo://test'));
  }
}