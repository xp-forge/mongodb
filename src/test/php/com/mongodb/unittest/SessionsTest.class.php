<?php namespace com\mongodb\unittest;

use com\mongodb\{Session, Int64};
use unittest\{Assert, Before, Test};
use util\UUID;

class SessionsTest {
  use WireTesting;

  private $id;

  /**
   * Runs an execute function inside a session
   * 
   * @param  [:var] $replies
   * @param  function(com.mongodb.io.Protocol, com.mongodb.Session): void $execute
   * @return com.mongodb.io.Protocol
   */
  private function session($replies, $execute) {
    $proto= $this->connect($replies, 'primary');

    $session= new Session($proto, $this->id);
    $execute($proto, $session);
    $session->close();

    return $proto;
  }

  /**
   * Runs a command twice in a transaction
   * 
   * @param  [:var] $replies
   * @param  [:var] $command
   * @param  ?string $options
   * @return com.mongodb.io.Protocol
   */
  private function transaction($replies, $command, $options= null) {
    return $this->session($replies, function($proto, $session) use($command, $options) {
      $transaction= $session->transaction($options);
      $proto->write($transaction, $command);
      $proto->write($transaction, $command);
      $transaction->commit();
    });
  }

  #[Before]
  public function id() {
    $this->id= new UUID('5f375bfe-af78-4af8-bb03-5d441a66a5fb');
  }

  #[Test]
  public function session_id_is_sent_along() {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->count(45), $this->ok()]];
    $count= ['count' => 'entries', '$db' => 'test'];
    $fixture= $this->session($replies, function($proto, $session) use($count) {
      $proto->read($session, $count);
    });

    $conn= $fixture->connections()[self::$PRIMARY];
    $context= ['lsid' => ['id' => $this->id], '$readPreference' => ['mode' => 'primary']];

    Assert::equals($count + $context, $conn->command(-2));
    Assert::equals(['endSessions' => [['id' => $this->id]], '$db' => 'admin'] + $context, $conn->command(-1));
  }

  #[Test]
  public function transaction_context_is_sent_along() {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->ok(), $this->ok(), $this->ok(), $this->ok()]];
    $update= ['update' => 'entries', 'updates' => [], '$db' => 'test'];
    $fixture= $this->transaction($replies, $update);

    $conn= $fixture->connections()[self::$PRIMARY];
    $context= ['lsid' => ['id' => $this->id], '$readPreference' => ['mode' => 'primary']];
    $txn= ['txnNumber' => new Int64(1), 'autocommit' => false];

    Assert::equals($txn + ['startTransaction' => true] + $update + $context, $conn->command(-4));
    Assert::equals($txn + $update + $context, $conn->command(-3));
    Assert::equals($txn + ['commitTransaction' => 1, '$db' => 'admin'] + $context, $conn->command(-2));
  }

  #[Test]
  public function readPreference_passed_to_transaction() {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->ok(), $this->ok(), $this->ok(), $this->ok()]];
    $update= ['update' => 'entries', 'updates' => [], '$db' => 'test'];
    $fixture= $this->transaction($replies, $update, 'readPreference=primaryPreferred');

    $conn= $fixture->connections()[self::$PRIMARY];
    $context= ['lsid' => ['id' => $this->id], '$readPreference' => ['mode' => 'primaryPreferred']];
    $txn= ['txnNumber' => new Int64(1), 'autocommit' => false];

    Assert::equals($txn + ['startTransaction' => true] + $update + $context, $conn->command(-4));
    Assert::equals($txn + $update + $context, $conn->command(-3));
    Assert::equals($txn + ['commitTransaction' => 1, '$db' => 'admin'] + $context, $conn->command(-2));
  }

  #[Test]
  public function timeoutMS_passed_to_transaction() {
    $replies= [self::$PRIMARY => [$this->hello(self::$PRIMARY), $this->ok(), $this->ok(), $this->ok(), $this->ok()]];
    $update= ['update' => 'entries', 'updates' => [], '$db' => 'test'];
    $fixture= $this->transaction($replies, $update, 'timeoutMS=5000');

    $conn= $fixture->connections()[self::$PRIMARY];
    $context= ['lsid' => ['id' => $this->id], '$readPreference' => ['mode' => 'primary']];
    $txn= ['txnNumber' => new Int64(1), 'autocommit' => false];

    Assert::equals($txn + ['startTransaction' => true] + $update + $context, $conn->command(-4));
    Assert::equals($txn + $update + $context, $conn->command(-3));
    Assert::equals($txn + ['commitTransaction' => 1, 'maxTimeMS' => new Int64(5000), '$db' => 'admin'] + $context, $conn->command(-2));
  }
}