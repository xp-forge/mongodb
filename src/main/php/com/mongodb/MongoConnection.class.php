<?php namespace com\mongodb;

use lang\{IllegalArgumentException, Value};
use peer\AuthenticationException;
use util\{Bytes, UUID};

class MongoConnection implements Value {
  private $proto;

  /**
   * Creates a new connection from a given connection string or by using
   * a given protocol instance.
   *
   * @param  string|com.mongodb.Protocol $arg
   */
  public function __construct($arg) {
    $this->proto= $arg instanceof Protocol ? $arg : new Protocol($arg);
  }

  /**
   * Connects and returns this connection
   *
   * @return self
   * @throws peer.AuthenticationException
   * @throws io.IOException
   */
  public function connect(): self {
    $this->proto->connect();
    return $this;
  }

  public function session($uuid= null) {
    $this->proto->connect();
    return new Session($this->proto, $uuid ?? UUID::randomUUID());
  }

  /**
   * Selects a given database, connecting if necessary
   *
   * @param  string $name The database name
   * @return com.mongodb.Database
   * @throws com.mongodb.Error
   */
  public function database(string $name): Database {
    $this->proto->connect();
    return new Database($this->proto, $name);
  }

  /**
   * Selects a given collection in a given database, connecting if necessary
   *
   * @param  string... $args A string "database.collection" or two arguments
   * @return com.mongodb.Collection
   * @throws com.mongodb.Error
   * @throws lang.IllegalArgumentException
   */
  public function collection(... $args): Collection {
    $namespace= implode('.', $args);
    if (2 === sscanf($namespace, "%[^.].%[^\r]", $database, $collection)) {
      $this->proto->connect();
      return new Collection($this->proto, $database, $collection);
    }

    throw new IllegalArgumentException('Cannot parse namespace "'.$namespace.'"');
  }

  /**
   * Returns a list of database information objects
   *
   * @return [:var][]
   * @throws com.mongodb.Error
   */
  public function databases() {
    $this->proto->connect();
    return $this->proto->msg(0, 0, ['listDatabases' => (object)[], '$db' => 'admin'])['body']['databases'];
  }

  /**
   * Close connection
   *
   * @return void
   */
  public function close() {
    $this->proto->close();
  }

  /** @return string */
  public function toString() { return nameof($this).'('.$this->proto->connection(false).')'; }

  /** @return string */
  public function hashCode() { return spl_object_hash($this); }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $this === $value ? 0 : 1;
  }
}