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
   * Inserts documents
   *
   * @param  com.mongodb.Document... $documents
   * @return int The number of inserts
   */
  public function insert(Document... $documents): int {
    $result= $this->proto->msg(0, 0, [
      'insert'    => $this->name,
      'documents' => $documents,
      'ordered'   => true,
      '$db'       => $this->database,
    ]);
    return $result['body']['n'];
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