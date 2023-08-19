<?php namespace com\mongodb;

use com\mongodb\io\{Commands, Protocol};
use com\mongodb\result\{Cursor, ChangeStream};
use lang\Value;
use util\Objects;

/**
 * A MongoDB database
 *
 * @test  com.mongodb.unittest.DatabaseTest
 */
class Database implements Value {
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
   * @param  com.mongodb.Options... $options
   * @return [:var][]
   * @throws com.mongodb.Error
   */
  public function collections(Options... $options) {
    $commands= Commands::reading($this->proto);
    $result= $commands->send($options, [
      'listCollections' => (object)[],
      '$db'             => $this->name
    ]);
    return new Cursor($commands, $options, $result['body']['cursor']);
  }

  /**
   * Watch for changes in this database
   *
   * @param  [:var][] $pipeline
   * @param  [:var] $params
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.ChangeStream
   * @throws com.mongodb.Error
   */
  public function watch(array $pipeline= [], array $params= [], Options... $options): ChangeStream {
    array_unshift($pipeline, ['$changeStream' => (object)$params]);

    $commands= Commands::reading($this->proto);
    $result= $commands->send($options, [
      'aggregate' => 1,
      'pipeline'  => $pipeline,
      'cursor'    => (object)[],
      '$db'       => $this->name,
    ]);
    return new ChangeStream($commands, $options, $result['body']['cursor']);
  }

  /** @return string */
  public function hashCode() {
    return 'D'.md5($this->name.'@'.$this->proto->dsn());
  }

  /** @return string */
  public function toString() {
    $options= $this->proto->options();
    return nameof($this).'<'.$this->name.'@'.$options['scheme'].'://'.$options['nodes'].'>';
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? Objects::compare(
      [$this->name, $this->proto->dsn()],
      [$value->name, $value->proto->dsn()]
    ) : 1;
  }
}