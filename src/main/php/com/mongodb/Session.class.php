<?php namespace com\mongodb;

use com\mongodb\io\Protocol;
use lang\Closeable;
use util\UUID;

/**
 * A client session
 *
 * @see   https://docs.mongodb.com/manual/reference/server-sessions/
 * @see   https://docs.mongodb.com/manual/core/read-isolation-consistency-recency/#std-label-sessions
 */
class Session implements Closeable {
  private $proto, $id;
  private $closed= false;

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

  /** @return void */
  public function close() {
    if ($this->closed) return;

    // Fire and forget: If the user has no session that match, the endSessions call has
    // no effect, see https://docs.mongodb.com/manual/reference/command/endSessions/
    $this->proto->read([
      'endSessions' => [['id' => $this->id]],
      '$db'         => 'admin'
    ]);
    $this->closed= true;
  }

  /** @return void */
  public function __destruct() {
    $this->close();
  }
}