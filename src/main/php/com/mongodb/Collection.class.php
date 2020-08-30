<?php namespace com\mongodb;

use com\mongodb\result\{Insert, Update, Delete, Cursor};

/**
 * A collection inside a database.
 *
 * @test  xp://com.mongodb.unittest.CollectionTest
 */
class Collection {
  private $proto, $database, $name;

  /** Creates a new collection */
  public function __construct(Protocol $proto, string $database, string $name) {
    $this->proto= $proto;
    $this->database= $database;
    $this->name= $name;
  }

  /** @return string */
  public function name() { return $this->name; }

  /** @return string */
  public function namespace() { return $this->database.'.'.$this->name; }

  /**
   * Runs a command in this database
   *
   * @param  string $name
   * @param  [:var] $params
   * @return var
   */
  public function command($name, array $params= []) {
    return $this->proto->msg(0, 0, [$name => $this->name] + $params + ['$db' => $this->database])['body'];
  }

  /**
   * Inserts documents. Creates an object ID if not present, modifying the
   * passed documents.
   *
   * @param  com.mongodb.Document|com.mongodb.Document[] $arg
   */
  public function insert($arg): Insert {
    $documents= is_array($arg) ? $arg : [$arg];

    // See https://docs.mongodb.com/manual/reference/method/db.collection.insert/#id-field:
    // Most drivers create an ObjectId and insert the _id field
    $ids= [];
    foreach ($documents as $document) {
      $ids[]= $document['_id'] ?? $document['_id']= ObjectId::create();
    }

    $result= $this->proto->msg(0, 0, [
      'insert'    => $this->name,
      'documents' => $documents,
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Insert($result['body'], $ids);
  }

  /**
   * Updates collection with given modifications. Use the `Operations` class
   * as an easy factory to choose whether to update one or more documents.
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  [:var] $statements Update operator expressions
   */
  public function update($query, $statements): Update {
    $result= $this->proto->msg(0, 0, [
      'update'    => $this->name,
      'updates'   => [['q' => is_array($query) ? $query : ['_id' => $query], 'u' => $statements]],
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Update($result['body']);
  }

  /**
   * Delete documents
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   */
  public function delete($query): Delete {
    $result= $this->proto->msg(0, 0, [
      'delete'    => $this->name,
      'deletes'   => [is_array($query) ? ['q' => $query, 'limit' => 0] : ['q' => ['_id' => $query], 'limit' => 1]],
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Delete($result['body']);
  }

  /**
   * Find documents in this collection
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @return com.mongodb.result.Cursor
   */
  public function find($query= []): Cursor {
    $result= $this->proto->msg(0, 0, [
      'find'   => $this->name,
      'filter' => is_array($query) ? ($query ?: (object)[]) : ['_id' => $query],
      '$db'    => $this->database,
    ]);
    return new Cursor($this->proto, $result['body']['cursor']);
  }

  /**
   * Count documents in this collection
   *
   * @param  [:var] $filter
   * @return int
   */
  public function count($filter= []): int {
    $count= ['$count' => 'n'];
    $result= $this->proto->msg(0, 0, [
      'aggregate' => $this->name,
      'pipeline'  => $filter ? [['$match' => $filter], $count] : [$count],
      'cursor'    => (object)[],
      '$db'       => $this->database,
    ]);
    return $result['body']['cursor']['firstBatch'][0]['n'];
  }

  /**
   * Returns distinct list of keys in this collection
   *
   * @param  string $key
   * @param  [:var] $filter
   * @return var[]
   */
  public function distinct($key, $filter= []): array {
    $distinct= ['$group' => ['_id' => 1, 'values' => ['$addToSet' => '$'.$key]]];
    $result= $this->proto->msg(0, 0, [
      'aggregate' => $this->name,
      'pipeline'  => $filter ? [['$match' => $filter], $distinct] : [$distinct],
      'cursor'    => (object)[],
      '$db'       => $this->database,
    ]);
    return $result['body']['cursor']['firstBatch'][0]['values'];
  }

  /**
   * Perfom aggregation over documents this collection
   *
   * @param  [:var][] $pipeline
   * @return com.mongodb.result.Cursor
   */
  public function aggregate($pipeline): Cursor {
    $result= $this->proto->msg(0, 0, [
      'aggregate' => $this->name,
      'pipeline'  => (array)$pipeline,
      'cursor'    => (object)[],
      '$db'       => $this->database,
    ]);
    return new Cursor($this->proto, $result['body']['cursor']);
  }
} 