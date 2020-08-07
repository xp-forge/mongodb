<?php namespace com\mongodb;

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
   * passed documents
   *
   * @param  com.mongodb.Document... $documents
   * @return com.mongodb.ObjectsId[] The inserted IDs
   */
  public function insert(Document... $documents): array {
    $ids= [];

    // See https://docs.mongodb.com/manual/reference/method/db.collection.insert/#id-field:
    // Most drivers create an ObjectId and insert the _id field
    foreach ($documents as $document) {
      $ids[]= $document['_id'] ?? $document['_id']= ObjectId::create();
    }

    $this->proto->msg(0, 0, [
      'insert'    => $this->name,
      'documents' => $documents,
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return $ids;
  }

  /**
   * Delete documents
   *
   * @param  [:var] $filter
   * @param  int? $limit An optional limit to apply, NULL for no limit
   * @return int The number of deletes
   */
  public function delete($filter= [], $limit= null): int {
    $result= $this->proto->msg(0, 0, [
      'delete'    => $this->name,
      'deletes'   => [['q' => $filter, 'limit' => (int)$limit]],
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return $result['body']['n'];
  }

  /**
   * Find documents in this collection
   *
   * @param  [:var] $filter
   * @return com.mongodb.Cursor
   */
  public function find($filter= []): Cursor {
    $result= $this->proto->msg(0, 0, [
      'find'   => $this->name,
      'filter' => (object)$filter,
      '$db'    => $this->database,
    ]);
    return new Cursor($this->proto, $result['body']['cursor']);
  }
} 