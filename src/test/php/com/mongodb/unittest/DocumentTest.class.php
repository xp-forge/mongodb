<?php namespace com\mongodb\unittest;

use com\mongodb\{Document, ObjectId};
use lang\IndexOutOfBoundsException;
use test\{Assert, Before, Expect, Test, Values};

class DocumentTest {
  const OID = '5f1dda9973edf2501751884b';

  /** @return iterable */
  private function representations() {
    yield [[], "com.mongodb.Document(-)@{\n}"];
    yield [['key' => 'value'], "com.mongodb.Document(-)@{\n  key: \"value\"\n}"];
    yield [['p' => 'more', 'n' => 6100], "com.mongodb.Document(-)@{\n  p: \"more\"\n  n: 6100\n}"];
    yield [['_id' => 6100], "com.mongodb.Document(6100)@{\n}"];
    yield [['_id' => new ObjectId(self::OID)], "com.mongodb.Document(".self::OID.")@{\n}"];
    yield [['_id' => ['PUBLIC']], "com.mongodb.Document([\"PUBLIC\"])@{\n}"];
    yield [['_id' => 6100, 'key' => 'value'], "com.mongodb.Document(6100)@{\n  key: \"value\"\n}"];
  }

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
    $id= new ObjectId(self::OID);
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

  #[Test]
  public function read_null_offset() {
    $fixture= new Document(['exists' => null]);
    Assert::equals(null, $fixture['exists']);
  }

  #[Test, Expect(class: IndexOutOfBoundsException::class, message: 'Undefined property "absent"')]
  public function read_non_existant_offset() {
    $fixture= new Document(['exists' => 'value']);
    $r= $fixture['absent'];
  }

  #[Test]
  public function read_non_exstant_offset_with_null_coalesce() {
    $fixture= new Document(['exists' => 'value']);
    Assert::null($fixture['absent'] ?? null);
  }

  #[Test, Values([['exists', true], ['absent', false]])]
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

  #[Test, Values(from: 'representations')]
  public function string_representation($fields, $expected) {
    Assert::equals($expected, (new Document($fields))->toString());
  }

  #[Test]
  public function sort_offset_array() {
    $fixture= new Document(['list' => [1, 3, 2]]);
    sort($fixture['list']);
    Assert::equals([1, 2, 3], $fixture['list']);
  }

  #[Test]
  public function append_to_offset_array() {
    $fixture= new Document(['list' => [1, 2, 3]]);
    $fixture['list'][]= 4;
    Assert::equals([1, 2, 3, 4], $fixture['list']);
  }
}