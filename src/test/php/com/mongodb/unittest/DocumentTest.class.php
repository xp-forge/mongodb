<?php namespace com\mongodb\unittest;

use com\mongodb\{Document, ObjectId};
use lang\IndexOutOfBoundsException;
use unittest\{Assert, Expect, Test, Values};

class DocumentTest {

  #[Test]
  public function can_create() {
    new Document();
  }

  #[Test]
  public function properties_empty_by_default() {
    Assert::equals([], (new Document())->properties());
  }

  #[Test, Values([[[]], [['key' => 'value']], [['a' => 'b'], ['c' => 'd']],])]
  public function properties($properties) {
    Assert::equals($properties, (new Document($properties))->properties());
  }

  #[Test]
  public function with_object_id() {
    $id= new ObjectId('5f1dda9973edf2501751884b');
    Assert::equals($id, (new Document(['_id' => $id]))->id());
  }

  #[Test]
  public function with_string_id() {
    $id= 'tag';
    Assert::equals($id, (new Document(['_id' => $id]))->id());
  }

  #[Test]
  public function read_offset() {
    $fixture= new Document(['exists' => 'value']);
    Assert::equals('value', $fixture['exists']);
  }

  #[Test, Expect(IndexOutOfBoundsException::class)]
  public function read_non_exstant_offset() {
    $fixture= new Document(['exists' => 'value']);
    $r= $fixture['absent'];
  }

  #[Test]
  public function read_non_exstant_offset_with_null_coalesce() {
    $fixture= new Document(['exists' => 'value']);
    Assert::null($fixture['absent'] ?? null);
  }

  #[Test, Values(['map' => ['exists' => true, 'absent' => false,]])]
  public function test_offset($key, $expected) {
    $fixture= new Document(['exists' => 'value']);
    Assert::equals($expected, isset($fixture[$key]));
  }

  #[Test]
  public function write_offset() {
    $fixture= new Document([]);
    $fixture['exists']= 'value';
    Assert::equals('value', $fixture['exists']);
  }

  #[Test]
  public function delete_offset() {
    $fixture= new Document(['exists' => 'value']);
    unset($fixture['exists']);
    Assert::false(isset($fixture['exists']));
  }
}