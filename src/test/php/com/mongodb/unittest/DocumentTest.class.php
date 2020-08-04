<?php namespace com\mongodb\unittest;

use com\mongodb\{Document, ObjectId};
use lang\IndexOutOfBoundsException;
use unittest\Assert;

class DocumentTest {

  #[@test]
  public function can_create() {
    new Document();
  }

  #[@test]
  public function properties_empty_by_default() {
    Assert::equals([], (new Document())->properties());
  }

  #[@test, @values([
  #  [[]],
  #  [['key' => 'value']],
  #  [['a' => 'b'], ['c' => 'd']],
  #])]
  public function properties($properties) {
    Assert::equals($properties, (new Document($properties))->properties());
  }

  #[@test]
  public function with_id() {
    $id= new ObjectId('5f1dda9973edf2501751884b');
    Assert::equals($id, (new Document(['_id' => $id]))->id());
  }

  #[@test]
  public function read_offset() {
    $fixture= new Document(['exists' => 'value']);
    Assert::equals('value', $fixture['exists']);
  }

  #[@test, @expect(IndexOutOfBoundsException::class)]
  public function read_non_exstant_offset() {
    $fixture= new Document(['exists' => 'value']);
    $r= $fixture['absent'];
  }

  #[@test]
  public function read_non_exstant_offset_with_null_coalesce() {
    $fixture= new Document(['exists' => 'value']);
    Assert::null($fixture['absent'] ?? null);
  }

  #[@test, @values(['map' => [
  #  'exists' => true,
  #  'absent' => false,
  #]])]
  public function test_offset($key, $expected) {
    $fixture= new Document(['exists' => 'value']);
    Assert::equals($expected, isset($fixture[$key]));
  }

  #[@test]
  public function write_offset() {
    $fixture= new Document([]);
    $fixture['exists']= 'value';
    Assert::equals('value', $fixture['exists']);
  }

  #[@test]
  public function delete_offset() {
    $fixture= new Document(['exists' => 'value']);
    unset($fixture['exists']);
    Assert::false(isset($fixture['exists']));
  }
}