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
   * Creates an instance using a protocol instance and given semantics.
   *
   * @param  com.mongodb.io.Protocol $proto
   * @param  string $semantics either "read" or "write"
   */
  public function __construct($proto, $semantics) {
    $this->proto= $proto;

    if ('read' === $semantics) {
      $this->conn= $proto->establish(
        $proto->candidates($proto->readPreference['mode']),
        'reading with '.$proto->readPreference['mode']
      );
    } else {
      $this->conn= $proto->establish([$proto->nodes['primary']], 'writing');
    }
  }

  /**
   * Sends a message
   * 
   * @param  ?com.mongodb.Session $session
   * @param  [:var] $sections
   * @return var
   * @throws com.mongodb.Error
   */
  public function send($session, $sections) {
    $session && $sections+= $session->send($this->proto);
    return $this->conn->message($sections, $this->proto->readPreference);
  }
}