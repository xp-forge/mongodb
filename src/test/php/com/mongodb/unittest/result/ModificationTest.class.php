<?php namespace com\mongodb\unittest\result;

use com\mongodb\result\Modification;
use com\mongodb\{Document, ObjectId};
use test\{Assert, Before, Test};

class ModificationTest {
  private $objectId;

  /** Creates a result */
  private function result(Document $document= null, $created= false) {
    if (null === $document) {
      $lastErrorObject= ['n' => 0, 'updatedExisting' => false];
      $value= null;
    } else if ($created) {
      $lastErrorObject= ['n' => 1, 'updatedExisting' => false, 'upserted' => $document->id()];
      $value= $document->properties();
    } else {
      $lastErrorObject= ['n' => 1, 'updatedExisting' => true];
      $value= $document->properties();
    }
    return ['lastErrorObject' => $lastErrorObject, 'value' => $value, 'ok' => 1];
  }

  #[Before]
  public function objectId() {
    $this->objectId= ObjectId::create();
  }

  #[Test]
  public function can_create() {
    new Modification($this->result());
  }

  #[Test]
  public function none_modified() {
    Assert::equals(0, (new Modification($this->result()))->modified());
  }

  #[Test]
  public function modified() {
    $doc= new Document(['test' => true]);
    Assert::equals(1, (new Modification($this->result($doc)))->modified());
  }

  #[Test]
  public function updated_existing() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::true((new Modification($this->result($doc)))->updatedExisting());
  }

  #[Test]
  public function created_new() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::false((new Modification($this->result($doc, true)))->updatedExisting());
  }

  #[Test]
  public function not_upserted() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::null((new Modification($this->result($doc)))->upserted());
  }

  #[Test]
  public function upserted_id() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::equals($this->objectId, (new Modification($this->result($doc, true)))->upserted());
  }

  #[Test]
  public function document() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::equals($doc, (new Modification($this->result($doc)))->document());
  }

  #[Test]
  public function no_document() {
    Assert::null((new Modification($this->result()))->document());
  }
}