<?php namespace com\mongodb\io;

use com\mongodb\Error;
use peer\ProtocolException;

/**
 * Ensures all message sent using this instance are executed against
 * the same socket connection, e.g. for cursors.
 *
 * @test com.mongodb.unittest.CommandsTest
 * @see  https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#cursors
 */
class Commands {
  private $conn, $proto, $rp, $retry;

  /**
   * Creates an instance using a protocol and connection instance.
   *
   * @param  com.mongodb.io.Protocol $proto
   * @param  [:var] $rp
   * @param  bool $retry
   */
  private function __construct($proto, $rp, $retry= true) {
    $this->conn= $proto->establish($proto->candidates($rp), 'commands with '.$rp['mode']);
    $this->proto= $proto;
    $this->rp= $rp;
    $this->retry= $retry;
  }

  /** @return com.mongodb.io.Connection */
  public function connection() { return $this->conn; }

  /** Creates an instance for reading */
  public static function reading(Protocol $proto): self {
    $proto->nodes || $proto->connect();
    return new self(
      $proto,
      $proto->readPreference,
      'true' === $proto->options()['params']['retryReads'] ?? 'true'
    );
  }

  /** Creates an instance for writing */
  public static function writing(Protocol $proto): self {
    $proto->nodes || $proto->connect();
    return new self(
      $proto,
      ['mode' => 'primary'],
      'true' === $proto->options()['params']['retryWrites'] ?? 'true'
    );
  }

  /**
   * Creates an instance using given semantics
   *
   * @param  com.mongodb.io.Protocol $proto
   * @param  string $semantics either "read" or "write"
   * @return self
   */
  public static function using($proto, $semantics) {
    if ('read' === $semantics) {
      return self::reading($proto);
    } else {
      return self::writing($proto);
    }
  }

  /**
   * Sends a message
   *
   * @param  com.mongodb.Options[] $options
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function send($options, $sections) {
    foreach ($options as $option) {
      $sections+= $option->send($this->proto);
    }

    // Only retry the very first command once in this sequence!
    $rp= $sections['$readPreference'] ?? $this->proto->readPreference;
    try {
      retry: $r= $this->conn->send(Connection::OP_MSG, "\x00\x00\x00\x00\x00", $sections, $rp);
      if (1 === (int)$r['body']['ok']) return $r;

      // Retry "NotWritablePrimary" errors, replacing the connection
      if ($this->retry && isset(Error::NOT_PRIMARY[$r['body']['code']])) {
        $this->proto->useCluster($this->conn->hello());
        $this->conn= $this->proto->establish($this->proto->candidates($this->rp), 'commands with '.$this->rp['mode']);
        $this->retry= false;
        goto retry;
      }

      throw Error::newInstance($r['body'], !$this->retry);
    } catch (ProtocolException $e) {
      if ($this->retry) {
        $this->conn->close();
        $this->conn= $this->proto->establish($this->proto->candidates($this->rp), 'commands with '.$this->rp['mode']);
        $this->retry= false;
        goto retry;
      }

      throw Error::protocol($this->conn, $e, !$this->retry);
    } finally {
      $this->retry= false;
    }
  }
}