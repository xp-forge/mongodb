<?php namespace com\mongodb\unittest\result;

use com\mongodb\result\Modification;
use com\mongodb\{Document, ObjectId};
use test\{Assert, Before, Test};

class ModificationTest {
  private $objectId;

  /** Creates a result from an update operation */
  private function update(Document $document= null, $created= false) {
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

  /** Creates a result from a remove operation */
  private function remove(Document $document= null) {
    if (null === $document) {
      $lastErrorObject= ['n' => 0];
      $value= null;
    } else {
      $lastErrorObject= ['n' => 1];
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
    new Modification($this->update());
  }

  #[Test]
  public function none_modified() {
    Assert::equals(0, (new Modification($this->update()))->modified());
  }

  #[Test]
  public function modified() {
    $doc= new Document(['test' => true]);
    Assert::equals(1, (new Modification($this->update($doc)))->modified());
  }

  #[Test]
  public function updated_existing() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::equals(Modification::UPDATED, ((new Modification($this->update($doc)))->kind()));
  }

  #[Test]
  public function created_new() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::equals(Modification::CREATED, (new Modification($this->update($doc, true)))->kind());
  }

  #[Test]
  public function not_upserted() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::null((new Modification($this->update($doc)))->upserted());
  }

  #[Test]
  public function upserted_id() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::equals($this->objectId, (new Modification($this->update($doc, true)))->upserted());
  }

  #[Test]
  public function document() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::equals($doc, (new Modification($this->update($doc)))->document());
  }

  #[Test]
  public function no_document() {
    Assert::null((new Modification($this->update()))->document());
  }

  #[Test]
  public function removed() {
    $doc= new Document(['_id' => $this->objectId, 'test' => true]);
    Assert::equals($doc, (new Modification($this->remove($doc)))->document());
  }

  #[Test]
  public function not_removed() {
    Assert::null((new Modification($this->remove()))->document());
  }

  #[Test]
  public function removal_kind() {
    Assert::equals(Modification::REMOVED, (new Modification($this->remove()))->kind());
  }
}