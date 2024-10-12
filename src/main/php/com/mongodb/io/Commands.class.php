<?php namespace com\mongodb\io;

use com\mongodb\Error;

/**
 * Ensures all message sent using this instance are executed against
 * the same socket connection, e.g. for cursors.
 *
 * @see https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#cursors
 */
class Commands {
  private $proto, $conn;
  private $retry= true;

  /**
   * Creates an instance using a protocol and connection instance.
   *
   * @param  com.mongodb.io.Protocol $proto
   * @param  com.mongodb.io.Connection $conn
   */
  private function __construct($proto, $conn) {
    $this->proto= $proto;
    $this->conn= $conn;
  }

  /** Creates an instance for reading */
  public static function reading(Protocol $proto): self {
    return new self($proto, $proto->establish(
      $proto->candidates($proto->readPreference),
      'reading with '.$proto->readPreference['mode']
    ));
  }

  /** Creates an instance for writing */
  public static function writing(Protocol $proto): self {
    return new self($proto, $proto->establish(
      [$proto->nodes['primary']],
      'writing'
    ));
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

    $rp= $section['$readPreference'] ?? $this->proto->readPreference;

    // Only retry the very first command once in this sequence!
    try {
      retry: $r= $this->conn->send(Connection::OP_MSG, "\x00\x00\x00\x00\x00", $sections, $rp);
      if (1 === (int)$r['body']['ok']) return $r;

      // Retry "NotWritablePrimary" errors, replacing the connection
      if ($this->retry && isset(Error::NOT_PRIMARY[$r['body']['code']])) {
        $this->proto->useCluster($this->conn->hello());
        $this->conn= $this->proto->establish([$this->proto->nodes['primary']], 'writing');
        $this->retry= false;
        goto retry;
      }

      throw Error::newInstance($r['body'], !$this->retry);
    } finally {
      $this->retry= false;
    }
  }
}