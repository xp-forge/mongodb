<?php namespace com\mongodb;

use com\mongodb\io\{Commands, Protocol};
use com\mongodb\result\{Insert, Update, Delete, Modification, Cursor, Run, ChangeStream};
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
   * @param  string $name
   * @param  [:var] $params
   * @param  string $semantics one of `read` or `write`
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Run
   * @throws com.mongodb.Error
   */
  public function run($name, array $params= [], $semantics= 'write', Options... $options) {
    $commands= Commands::using($this->proto, $semantics);
    return new Run(
      $commands,
      $options,
      $commands->send($options, [$name => $this->name] + $params + ['$db' => $this->database])
    );
  }

  /**
   * Inserts documents. Creates an object ID if not present, modifying the
   * passed documents.
   *
   * @param  com.mongodb.Document|com.mongodb.Document[] $arg
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Insert
   * @throws com.mongodb.Error
   */
  public function insert($arg, Options... $options): Insert {
    $documents= is_array($arg) ? $arg : [$arg];

    // See https://docs.mongodb.com/manual/reference/method/db.collection.insert/#id-field:
    // Most drivers create an ObjectId and insert the _id field
    $ids= [];
    foreach ($documents as $document) {
      $ids[]= $document['_id']??= ObjectId::create();
    }

    $result= $this->proto->write($options, [
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
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Update
   * @throws com.mongodb.Error
   */
  public function upsert($query, $arg, Options... $options): Update {
    $result= $this->proto->write($options, [
      'update'    => $this->name,
      'updates'   => [[
        'q'      => is_array($query) ? $query : ['_id' => $query],
        'u'      => $arg,
        'upsert' => true,
        'multi'  => false,
      ]],
      '$db'       => $this->database,
    ]);
    return new Update($result['body']);
  }

  /**
   * Updates collection with given statements.
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  [:var] $statements Update operator expressions
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Update
   * @throws com.mongodb.Error
   */
  public function update($query, $statements, Options... $options): Update {
    $result= $this->proto->write($options, [
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
   * Modifies collection and returns a `Modification` instance with the modified
   * document.
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  [:var]|com.mongodb.Document $arg Update operator expressions or document
   * @param  bool $upsert
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Modification
   * @throws com.mongodb.Error
   */
  public function modify($query, $arg, $upsert= false, Options... $options): Modification {
    $result= $this->proto->write($options, [
      'findAndModify' => $this->name,
      'query'         => is_array($query) ? $query : ['_id' => $query],
      'update'        => $arg,
      'new'           => true,
      'upsert'        => $upsert,
      '$db'           => $this->database,
    ]);
    return new Modification($result['body']);
  }

  /**
   * Delete documents
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Delete
   * @throws com.mongodb.Error
   */
  public function delete($query, Options... $options): Delete {
    $result= $this->proto->write($options, [
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
   * Modifies collection and returns a `Modification` instance with the removed
   * document.
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  [:var]|com.mongodb.Document $arg Update operator expressions or document
   * @param  bool $upsert
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Modification
   * @throws com.mongodb.Error
   */
  public function remove($query, Options... $options): Modification {
    $result= $this->proto->write($options, [
      'findAndModify' => $this->name,
      'query'         => is_array($query) ? $query : ['_id' => $query],
      'remove'        => true,
      '$db'           => $this->database,
    ]);
    return new Modification($result['body']);
  }

  /**
   * Find documents in this collection
   *
   * @param  string|com.mongodb.ObjectId|[:var] $query
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Cursor
   * @throws com.mongodb.Error
   */
  public function find($query= [], Options... $options): Cursor {
    $commands= Commands::reading($this->proto);
    $result= $commands->send($options, [
      'find'   => $this->name,
      'filter' => is_array($query) ? ($query ?: (object)[]) : ['_id' => $query],
      '$db'    => $this->database,
    ]);
    return new Cursor($commands, $options, $result['body']['cursor']);
  }

  /**
   * Count documents in this collection
   *
   * @param  [:var] $filter
   * @param  com.mongodb.Options... $options
   * @return int
   * @throws com.mongodb.Error
   */
  public function count($filter= [], Options... $options): int {
    $count= ['$count' => 'n'];
    $result= $this->proto->read($options, [
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
   * @param  com.mongodb.Options... $options
   * @return var[]
   * @throws com.mongodb.Error
   */
  public function distinct($key, $filter= [], Options... $options): array {
    $distinct= ['$group' => ['_id' => 1, 'values' => ['$addToSet' => '$'.$key]]];
    $result= $this->proto->read($options, [
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
   * @param  com.mongodb.Options... $options
   * @return com.mongodb.result.Cursor
   * @throws com.mongodb.Error
   */
  public function aggregate(array $pipeline= [], Options... $options): Cursor {
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
      $commands= Commands::writing($this->proto);
    } else {
      $commands= Commands::reading($this->proto);
    }

    $result= $commands->send($options, $sections);
    return new Cursor($commands, $options, $result['body']['cursor']);
  }

  /**
   * Watch for changes in this collection
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
      'aggregate' => $this->name,
      'pipeline'  => $pipeline,
      'cursor'    => (object)[],
      '$db'       => $this->database,
    ]);
    return new ChangeStream($commands, $options, $result['body']['cursor']);
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