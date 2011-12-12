<?php
// Safe class config...
$EXLITERPC_SAFE_CLASSES = array('DateTime', 'Person');

require_once('exliterpc.php');

class Person {
  public $name;
  public $age;
  
  public function __construct($name, $age) {
    $this->name = $name;
    $this->age = $age;
  }
}

class Tree {
  public $name;
  public $length;
  
  public function __construct($name, $length) {
    $this->name = $name;
    $this->length = $length;
  }
}

$test1 = serialize(array(new DateTime(), new Person('john', 23)));
if (!_exliterpc_verify_serialized_data_safe($test1)) {
  echo 'FAILED: test1'; exit;
}

$test2 = serialize(array(new DateTime(), new Tree('tree1', 130)));
if (_exliterpc_verify_serialized_data_safe($test2)) {
  echo 'FAILED: test2'; exit;
}

echo 'OK';
