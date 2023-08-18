<?php namespace com\mongodb;

use com\mongodb\io\Protocol;
use lang\{Closeable, IllegalStateException, Value, Throwable};
use util\{UUID, Objects};

/**
 * A client session
 *
 * @see   https://docs.mongodb.com/manual/reference/server-sessions/
 * @see   https://docs.mongodb.com/manual/core/read-isolation-consistency-recency/#std-label-sessions
 * @see   https://github.com/mongodb/specifications/blob/master/source/sessions/driver-sessions.rst
 * @test  com.mongodb.unittest.SessionTest
 * @test  com.mongodb.unittest.SessionsTest
 */
class Session extends Options implements Value, Closeable {
  private $proto, $id;
  private $closed= false;
  private $transaction= ['n' => 0];

  /**
   * Creates a new session
   *
   * @param  com.mongodb.io.Protocol
   * @param  string|util.UUID $id
   */
  public function __construct(Protocol $proto, $id) {
    $this->proto= $proto;
    $this->id= $id instanceof UUID ? $id : new UUID($id);
  }

  /** Returns session identifier */
  public function id(): UUID { return $this->id; }

  /** Returns whether this session was closed */
  public function closed(): bool { return $this->closed; }

  /**
   * Starts a multi-document transaction associated with the session. At any
   * given time, you can have at most one open transaction for a session.
   *
   * @see    https://github.com/mongodb/specifications/blob/master/source/transactions/transactions.rst#transactionoptions
   * @param  ?string $options
   * @return self
   * @throws lang.IllegalStateException if a transaction has already been started
   */
  public function transaction($options= null): self {
    if (isset($this->transaction['context'])) {
      throw new IllegalStateException('Cannot start more than one transaction on a session');
    }

    null === $options ? $params= [] : parse_str($options, $params);
    $this->transaction['context']= [
      'txnNumber'        => new Int64(++$this->transaction['n']),
      'autocommit'       => false,
      'startTransaction' => true,
    ];

    // Overwrite readPreference, defaults to inheriting from client
    isset($params['readPreference']) && $this->transaction['context']['$readPreference']= ['mode' => $params['readPreference']];

    // Support timeoutMS and the deprecated maxCommitTimeMS
    $timeout= $params['timeoutMS'] ?? $params['maxCommitTimeMS'] ?? null;
    $this->transaction['t']= null === $timeout ? [] : ['maxTimeMS' => new Int64($timeout)];

    return $this;
  }

  /**
   * Commits a transaction
   *
   * @return void
   * @throws lang.IllegalStateException if no transaction is active
   * @throws com.mongodb.Error
   */
  public function commit() {
    if (!isset($this->transaction['context'])) {
      throw new IllegalStateException('No active transaction');
    }

    try {
      if (!isset($this->transaction['context']['startTransaction'])) {
        $this->proto->write($this, ['commitTransaction' => 1, '$db' => 'admin'] + $this->transaction['t'] + $this->transaction['context']);
      }
    } finally {
      unset($this->transaction['context']);
    }
  }

  /**
   * Aborts a transaction
   *
   * @return void
   * @throws lang.IllegalStateException if no transaction is active
   * @throws com.mongodb.Error
   */
  public function abort() {
    if (!isset($this->transaction['context'])) {
      throw new IllegalStateException('No active transaction');
    }

    try {
      if (!isset($this->transaction['context']['startTransaction'])) {
        $this->proto->write($this, ['abortTransaction' => 1, '$db' => 'admin'] + $this->transaction['context']);
      }
    } finally {
      unset($this->transaction['context']);
    }
  }

  /**
   * Returns fields to be sent along with the command. Used by Protocol class.
   *
   * @param  com.mongodb.io.Protocol
   * @return [:var]
   * @throws lang.IllegalStateException
   */
  public function send($proto) {
    if ($proto !== $this->proto) {
      throw new IllegalStateException('Session was created by a different client');
    }

    // When constructing the first command within a transaction, drivers MUST
    // add the lsid, txnNumber, startTransaction, and autocommit fields. When 
    // constructing any other command within a transaction, drivers MUST add
    // the lsid, txnNumber, and autocommit fields.
    $fields= ['lsid' => ['id' => $this->id]];
    if (isset($this->transaction['context'])) {
      $fields+= $this->transaction['context'];
      unset($this->transaction['context']['startTransaction']);
    }
    return $fields;
  }

  /** @return void */
  public function close() {
    if ($this->closed) return;

    // Should there be an active running transaction, abort it.
    if (isset($this->transaction['context'])) {
      try {
        $this->proto->write($this, ['abortTransaction' => 1, '$db' => 'admin'] + $this->transaction['context']);
      } catch (Throwable $ignored) {
        // NOOP
      }
      unset($this->transaction['context']);
    }

    // Fire and forget: If the user has no session that match, the endSessions call has
    // no effect, see https://docs.mongodb.com/manual/reference/command/endSessions/
    try {
      $this->proto->write($this, ['endSessions' => [['id' => $this->id]], '$db' => 'admin']);
    } catch (Throwable $ignored) {
      // NOOP
    }
    $this->closed= true;
  }

  /** @return string */
  public function hashCode() {
    return 'S'.$this->id->hashCode();
  }

  /** @return string */
  public function toString() {
    $context= isset($this->transaction['context']) ? ', transaction: '.Objects::stringOf($this->transaction['context']) : '';
    return nameof($this).'(id: '.$this->id->hashCode().$context.')';
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->id->compareTo($value->id) : 1;
  }

  /** @return void */
  public function __destruct() {
    $this->close();
  }
}