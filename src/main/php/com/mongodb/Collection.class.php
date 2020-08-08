<?php namespace com\mongodb;

use com\mongodb\result\{Insert, Update, Cursor};

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
   * Inserts documents. Creates an object ID if not present, modifying the
   * passed documents.
   */
  public function insert(Document... $documents): Insert {
    $ids= [];

    // See https://docs.mongodb.com/manual/reference/method/db.collection.insert/#id-field:
    // Most drivers create an ObjectId and insert the _id field
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
   */
  public function update(Modifications ...$modifications): Update {
    $result= $this->proto->msg(0, 0, [
      'update'    => $this->name,
      'updates'   => $modifications,
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Update($result['body']);
  }

  /**
   * Delete documents
   *
   * @param  [:var] $filter
   * @param  int? $limit An optional limit to apply, NULL for no limit
   */
  public function delete($filter= [], $limit= null): Delete {
    $result= $this->proto->msg(0, 0, [
      'delete'    => $this->name,
      'deletes'   => [['q' => $filter ?: (object)[], 'limit' => (int)$limit]],
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return new Delete($result['body']);
  }

  /**
   * Find documents in this collection
   *
   * @param  [:var] $filter
   * @return com.mongodb.result.Cursor
   */
  public function find($filter= []): Cursor {
    $result= $this->proto->msg(0, 0, [
      'find'   => $this->name,
      'filter' => $filter ?: (object)[],
      '$db'    => $this->database,
    ]);
    return new Cursor($this->proto, $result['body']['cursor']);
  }
} 