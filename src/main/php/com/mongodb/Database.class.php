<?php namespace com\mongodb;

use com\mongodb\io\Protocol;

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
   * @return [:var][]
   * @throws com.mongodb.Error
   */
  public function collections() {
    $result= $this->proto->read([
      'listCollections' => (object)[],
      '$db'             => $this->name
    ]);
    return new Cursor($this->proto, $result['body']['cursor']);
  }
}