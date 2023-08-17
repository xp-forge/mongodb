<?php namespace com\mongodb;

use com\mongodb\io\{Commands, Protocol};
use com\mongodb\result\{Insert, Update, Delete, Cursor, Run, ChangeStream};
use lang\Value;
use util\Objects;

/**
 * A collection inside a database.
 *
 * @test  xp://com.mongodb.unittest.CollectionTest
 */
class Collection implements Value {
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
   * @deprecated Use `run()` instead!
   * @param  string $name
   * @param  [:var] $params
   * @param  ?com.mongodb.Session $session
   * @return var
   * @throws com.mongodb.Error
   */
  public function command($name, array $params= [], Session $session= null) {
    return $this->proto->write($session, [$name => $this->name] + $params + ['$db' => $this->database])['body'];
  }

  /**
   * Runs a command in this database
   *
   * @param  string $name
   * @param  [:var] $params
   * @param  string $semantics one of `read` or `write`
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Run
   * @throws com.mongodb.Error
   */
  public function run($name, array $params= [], $semantics= 'write', Session $session= null) {
    $commands= new Commands($this->proto, $semantics);
    return new Run(
      $commands,
      $session,
      $commands->send($session, [$name => $this->name] + $params + ['$db' => $this->database])
    );
  }

  /**
   * Inserts documents. Creates an object ID if not present, modifying the
   * passed documents.
   *
   * @param  com.mongodb.Document|com.mongodb.Document[] $arg
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Insert
   * @throws com.mongodb.Error
   */
  public function insert($arg, Session $session= null): Insert {
    $documents= is_array($arg) ? $arg : [$arg];

    // See https://docs.mongodb.com/manual/reference/method/db.collection.insert/#id-field:
    // Most drivers create an ObjectId and insert the _id field
    $ids= [];
    foreach ($documents as $document) {
      $ids[]= $document['_id'] ?? $document['_id']= ObjectId::create();
    }

    $result= $this->proto->write($session, [
      'insert'    => $this->name,
      'documents' => $documents,
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Insert($result['body'], $ids);
  }

  /**
   * Upserts collection with given modifications.
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  [:var]|com.mongodb.Document $arg Update operator expressions or document
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Update
   * @throws com.mongodb.Error
   */
  public function upsert($query, $arg, Session $session= null): Update {
    $result= $this->proto->write($session, [
      'update'    => $this->name,
      'updates'   => [[
        'q'      => $query instanceof ObjectId ? ['_id' => $query] : $query,
        'u'      => $arg,
        'upsert' => true,
        'multi'  => false
      ]],
      '$db'       => $this->database,
    ]);
    return new Update($result['body']);
  }

  /**
   * Updates collection with given modifications.
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  [:var] $statements Update operator expressions
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Update
   * @throws com.mongodb.Error
   */
  public function update($query, $statements, Session $session= null): Update {
    $result= $this->proto->write($session, [
      'update'    => $this->name,
      'updates'   => [['u' => $statements] + (is_array($query)
        ? ['q' => $query, 'multi' => true]
        : ['q' => ['_id' => $query], 'multi' => false]
      )],
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Update($result['body']);
  }

  /**
   * Delete documents
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Delete
   * @throws com.mongodb.Error
   */
  public function delete($query, Session $session= null): Delete {
    $result= $this->proto->write($session, [
      'delete'    => $this->name,
      'deletes'   => [is_array($query)
        ? ['q' => $query, 'limit' => 0]
        : ['q' => ['_id' => $query], 'limit' => 1]
      ],
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Delete($result['body']);
  }

  /**
   * Find documents in this collection
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Cursor
   * @throws com.mongodb.Error
   */
  public function find($query= [], Session $session= null): Cursor {
    $commands= new Commands($this->proto, 'read');
    $result= $commands->send($session, [
      'find'   => $this->name,
      'filter' => is_array($query) ? ($query ?: (object)[]) : ['_id' => $query],
      '$db'    => $this->database,
    ]);
    return new Cursor($commands, $session, $result['body']['cursor']);
  }

  /**
   * Count documents in this collection
   *
   * @param  [:var] $filter
   * @param  ?com.mongodb.Session $session
   * @return int
   * @throws com.mongodb.Error
   */
  public function count($filter= [], Session $session= null): int {
    $count= ['$count' => 'n'];
    $result= $this->proto->read($session, [
      'aggregate' => $this->name,
      'pipeline'  => $filter ? [['$match' => $filter], $count] : [$count],
      'cursor'    => (object)[],
      '$db'       => $this->database,
    ]);
    return $result['body']['cursor']['firstBatch'][0]['n'] ?? 0;
  }

  /**
   * Returns distinct list of keys in this collection
   *
   * @param  string $key
   * @param  [:var] $filter
   * @param  ?com.mongodb.Session $session
   * @return var[]
   * @throws com.mongodb.Error
   */
  public function distinct($key, $filter= [], Session $session= null): array {
    $distinct= ['$group' => ['_id' => 1, 'values' => ['$addToSet' => '$'.$key]]];
    $result= $this->proto->read($session, [
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
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.Cursor
   * @throws com.mongodb.Error
   */
  public function aggregate(array $pipeline= [], Session $session= null): Cursor {
    $sections= [
      'aggregate' => $this->name,
      'pipeline'  => $pipeline,
      'cursor'    => (object)[],
      '$db'       => $this->database,
    ];

    // Look at last pipeline stage: If it's $out or $merge, the pipeline will
    // use write semantics, otherwise, it will be used for reading only!
    //
    // TODO check https://jira.mongodb.org/browse/DRIVERS-823,
    // https://docs.mongodb.com/manual/reference/operator/aggregation/out &
    // https://docs.mongodb.com/manual/reference/operator/aggregation/merge/ 
    $last= $pipeline ? key($pipeline[sizeof($pipeline) - 1]) : null;
    if ('$out' === $last || '$merge' === $last) {
      $commands= new Commands($this->proto, 'write');
    } else {
      $commands= new Commands($this->proto, 'read');
    }

    $result= $commands->send($session, $sections);
    return new Cursor($commands, $session, $result['body']['cursor']);
  }

  /**
   * Watch for changes in this collection
   *
   * @param  [:var][] $pipeline
   * @param  [:var] $options
   * @param  ?com.mongodb.Session $session
   * @return com.mongodb.result.ChangeStream
   * @throws com.mongodb.Error
   */
  public function watch(array $pipeline= [], array $options= [], Session $session= null): ChangeStream {
    array_unshift($pipeline, ['$changeStream' => (object)$options]);

    $commands= new Commands($this->proto, 'read');
    $result= $commands->send($session, [
      'aggregate' => $this->name,
      'pipeline'  => $pipeline,
      'cursor'    => (object)[],
      '$db'       => $this->database,
    ]);
    return new ChangeStream($commands, $session, $result['body']['cursor']);
  }

  /** @return string */
  public function hashCode() {
    return 'C'.md5($this->database.'.'.$this->name.'@'.$this->proto->dsn());
  }

  /** @return string */
  public function toString() {
    $options= $this->proto->options();
    return nameof($this).'<'.$this->database.'.'.$this->name.'@'.$options['scheme'].'://'.$options['nodes'].'>';
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? Objects::compare(
      [$this->database, $this->name, $this->proto->dsn()],
      [$value->database, $value->name, $value->proto->dsn()]
    ) : 1;
  }
}