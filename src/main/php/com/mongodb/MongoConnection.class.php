<?php namespace com\mongodb;

use com\mongodb\io\{Commands, Protocol};
use com\mongodb\result\{ChangeStream, Run};
use lang\{IllegalArgumentException, Value};
use peer\AuthenticationException;
use util\{Bytes, UUID, Objects};

/**
 * Entry point for MongoDB connections
 *
 * ```php
 * $conn= new MongoConnection('mongo://localhost');
 * $conn= new MongoConnection('mongo+srv://user:pass@example.mongodb.net?readPreference=primary');
 *
 * foreach ($conn->collection('test.products')->find([]) as $product) {
 *   // ...
 * }
 * ```
 *
 * @see   https://docs.mongodb.com/manual/reference/connection-string/
 * @test  com.mongodb.unittest.MongoConnectionTest
 */
class MongoConnection implements Value {
  private $proto;

  /**
   * Creates a new connection from a given connection string or by using
   * a given protocol instance.
   *
   * @param  string|com.mongodb.io.Protocol $arg
   */
  public function __construct($arg) {
    $this->proto= $arg instanceof Protocol ? $arg : new Protocol($arg);
  }

  /** @return com.mongodb.io.Protocol */
  public function protocol() { return $this->proto; }

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

  /**
   * Runs a command in the `admin` database.
   *
   * @param  string $name
   * @param  [:var] $arguments
   * @param  string $method one of `read` or `write`
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Run
   * @throws com.mongodb.Error
   */
  public function run($name, array $arguments= [], $method= 'write', Session $session= null) {
    $this->proto->connect();

    $commands= new Commands($this->proto, $method);
    return new Run(
      $commands,
      $session,
      $commands->send($session, [$name => 1] + $params + ['$db' => 'admin'])
    );
  }

  /**
   * Creates a session, optionally supplying a session UUID.
   *
   * @param  ?string|util.UUID $uuid
   * @return com.mongodb.Session
   */
  public function session($uuid= null) {
    $this->proto->connect();

    // From the spec: "Drivers SHOULD generate session IDs locally if possible
    // instead of running the startSession command, since running the command
    // requires a network round trip".
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
   * @see    https://docs.mongodb.com/manual/reference/command/listDatabases/
   * @param  ?string|com.mongodb.Regex|[:string|com.mongodb.Regex] $filter
   * @return ?com.mongodb.Session $session
   * @return iterable
   * @throws com.mongodb.Error
   */
  public function databases($filter= null, $session= null) {
    $this->proto->connect();

    $request= ['listDatabases' => 1, '$db' => 'admin'];
    if (null === $filter) {
      // NOOP
    } else if (is_array($filter)) {
      $request+= ['filter' => $filter];
    } else {
      $request+= ['filter' => ['name' => $filter]];
    }

    foreach ($this->proto->read($session, $request)['body']['databases'] as $d) {
      yield $d['name'] => [
        'name'       => $d['name'],
        'sizeOnDisk' => $d['sizeOnDisk'] instanceof Int64 ? $d['sizeOnDisk'] : new Int64($d['sizeOnDisk']),
        'empty'      => $d['empty'],
        'shards'     => $d['shards'] ?? null,
      ];
    }
  }

  /**
   * Watch for changes in all databases.
   *
   * @param  [:var][] $pipeline
   * @param  [:var] $options
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.ChangeStream
   * @throws com.mongodb.Error
   */
  public function watch(array $pipeline= [], array $options= [], Session $session= null): ChangeStream {
    $this->proto->connect();

    array_unshift($pipeline, ['$changeStream' => ['allChangesForCluster' => true] + $options]);

    $commands= new Commands($this->proto, 'read');
    $result= $commands->send($session, [
      'aggregate' => 1,
      'pipeline'  => $pipeline,
      'cursor'    => (object)[],
      '$db'       => 'admin',
    ]);
    return new ChangeStream($commands, $session, $result['body']['cursor']);
  }

  /** @return string */
  public function hashCode() { return spl_object_hash($this); }

  /** @return string */
  public function toString() {
    return nameof($this).'('.$this->proto->dsn(false).')@'.Objects::stringOf($this->proto->nodes);
  }

  /**
   * Compare
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $this === $value ? 0 : 1;
  }

  /**
   * Close connection
   *
   * @return void
   */
  public function close() {
    $this->proto->close();
  }
}