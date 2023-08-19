<?php namespace com\mongodb\io;

/**
 * Ensures all message sent using this instance are executed against
 * the same socket connection, e.g. for cursors.
 *
 * @see https://github.com/mongodb/specifications/blob/master/source/server-selection/server-selection.rst#cursors
 */
class Commands {
  private $proto, $conn;

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
    return $this->conn->message($sections, $rp);
  }
}