<?php namespace com\mongodb;

use com\mongodb\io\Protocol;
use com\mongodb\result\{Cursor, ChangeStream};

class Database {
  private $proto, $name;

  /** Creates a new database instance */
  public function __construct(Protocol $proto, string $name) {
    $this->proto= $proto;
    $this->name= $name;
  }

  /** @return string */
  public function name() { return $this->name; }

  /** Returns a collection inside this database with a given name */ 
  public function collection(string $name): Collection {
    return new Collection($this->proto, $this->name, $name);
  }

  /**
   * Returns a list of database information objects
   *
   * @param  ?com.mongodb.Session $session
   * @return [:var][]
   * @throws com.mongodb.Error
   */
  public function collections($session= null) {
    $result= $this->proto->read($session, [
      'listCollections' => (object)[],
      '$db'             => $this->name
    ]);
    return new Cursor($this->proto, $session, $result['body']['cursor']);
  }

  /**
   * Watch for changes in this database
   *
   * @param  [:var][] $pipeline
   * @param  [:var] $options
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.ChangeStream
   * @throws com.mongodb.Error
   */
  public function watch(array $pipeline= [], array $options= [], Session $session= null): ChangeStream {
    array_unshift($pipeline, ['$changeStream' => (object)$options]);
    $result= $this->proto->read($session, [
      'aggregate' => 1,
      'pipeline'  => $pipeline,
      'cursor'    => (object)[],
      '$db'       => $this->name,
    ]);
    return new ChangeStream($this->proto, $session, $result['body']['cursor']);
  }
}