<?php namespace com\mongodb\unittest;

use com\mongodb\{Session, Int64};
use unittest\{Assert, Test};
use util\UUID;

class SessionsTest {
  const SESSION = '5f375bfe-af78-4af8-bb03-5d441a66a5fb';

  use WireTesting;

  #[Test]
  public function session_id_is_sent_along() {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY), $this->count(45), $this->ok()],
      self::$SECONDARY1 => [],
      self::$SECONDARY2 => [],
    ];
    $fixture= $this->connect($replicaSet, 'primary');
    $count= ['count' => 'entries', '$db' => 'test'];

    $id= new UUID(self::SESSION);
    $session= new Session($fixture, $id);
    $fixture->read($session, $count);
    $session->close();

    $conn= $fixture->connections()[self::$PRIMARY];
    $context= ['lsid' => ['id' => $id], '$readPreference' => ['mode' => 'primary']];

    Assert::equals($count + $context, $conn->command(-2));
    Assert::equals(['endSessions' => [['id' => $id]], '$db' => 'admin'] + $context, $conn->command(-1));
  }

  #[Test]
  public function transaction_context_is_sent_along() {
    $replicaSet= [
      self::$PRIMARY    => [$this->hello(self::$PRIMARY), $this->ok(), $this->ok(), $this->ok(), $this->ok()],
      self::$SECONDARY1 => [],
      self::$SECONDARY2 => [],
    ];
    $fixture= $this->connect($replicaSet, 'primary');
    $update= ['update' => 'entries', 'updates' => [], '$db' => 'test'];

    $id= new UUID(self::SESSION);
    $transaction= (new Session($fixture, $id))->transaction();
    $fixture->write($transaction, $update);
    $fixture->write($transaction, $update);
    $transaction->commit();

    $conn= $fixture->connections()[self::$PRIMARY];
    $context= ['lsid' => ['id' => $id], '$readPreference' => ['mode' => 'primary']];
    $txn= ['txnNumber' => new Int64(1), 'autocommit' => false];

    Assert::equals($txn + ['startTransaction' => true] + $update + $context, $conn->command(-3));
    Assert::equals($txn + $update + $context, $conn->command(-2));
    Assert::equals($txn + ['commitTransaction' => 1, '$db' => 'admin'] + $context, $conn->command(-1));
  }
}