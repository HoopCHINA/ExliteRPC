<?php
// Safe class config...
$EXLITERPC_SAFE_CLASSES = array('Person');

require_once('exliterpc.php');

class Person {
  public $name;
  public $age;
  
  public function __construct($name, $age) {
    $this->name = $name;
    $this->age = $age;
  }
}

class FooBar {}

class helloworld {
  public function eko($i) {
    return $i;
  }
  public function hello() {
    return 'hello, world!';
  }
}

// Server side
if (isset($_GET['m']) && $_GET['m'] === 'server') {
  $server = new ExliteRPC_Server(new helloworld(), 10, 'sha1salt');
  $server->handle();
  exit;
}

// Client side
$remote_url = 'http://localhost'.$_SERVER["SCRIPT_NAME"].'?m=server';

// Empty request payload
$resp = @file_get_contents($remote_url);
if ($resp !== FALSE) {
  echo 'FAILED: empty request case'; exit;
}

// Normal requests
$proxy = new ExliteRPC($remote_url, 10, 'sha1salt');
try {
  $resp = $proxy->hello();
  if ($resp !== 'hello, world!') {
    echo 'FAILED: hello() case'; exit;
  }
  $resp = $proxy->eko('hell');
  if ($resp !== 'hell') {
    echo 'FAILED: eko() case'; exit;
  }
}
catch (ExliteRPC_Exception $e) {
  echo 'FAILED: normal requests case'; exit;
}

// Safe serialize objects
$proxy = new ExliteRPC($remote_url, 10, 'sha1salt');
try {
  $resp = $proxy->eko(new Person('john', 23));
  if (!($resp instanceof Person)
      || $resp->name !== 'john'
      || $resp->age !== 23) {
    echo 'FAILED: Person serialize requests case'; exit;
  }
}
catch (ExliteRPC_NetworkException $e) {
  echo 'FAILED: safe object serialize requests case'; exit;
}

// Unsafe serialize objects
$proxy = new ExliteRPC($remote_url, 10, 'sha1salt');
try {
  $resp = $proxy->eko(new FooBar());
  echo 'FAILED: FooBar serialize case can not reach'; exit;
}
catch (ExliteRPC_NetworkException $e) {
  if ($e->getMessage() !== "Could not open ${remote_url}") {
    echo 'FAILED: unsafe object serialize requests case'; exit;
  }
}

// Nonexist method
$proxy = new ExliteRPC($remote_url, 10, 'sha1salt');
try {
  $resp = $proxy->nonexist();
  echo 'FAILED: nonexist() case can not reach'; exit;
}
catch (ExliteRPC_NetworkException $e) {
  if ($e->getMessage() !== "Could not open ${remote_url}") {
    echo 'FAILED: nonexist() case'; exit;
  }
}

// Non exist remote url
$proxy = new ExliteRPC('http://nonexist.uri/', 10, 'sha1salt');
try {
  $resp = $proxy->nonexist();
  echo 'FAILED: nonexist uri case can not reach'; exit;
}
catch (ExliteRPC_NetworkException $e) {
  if ($e->getMessage() !== "Could not open http://nonexist.uri/") {
    echo 'FAILED: nonexist uri case'; exit;
  }
}

// SHA1 salt wrong
$proxy = new ExliteRPC($remote_url, 10, 'sha1salt$wrong');
try {
  $resp = $proxy->hello();
  echo 'FAILED: wrong sha1::salt case can not reach'; exit;
}
catch (ExliteRPC_NetworkException $e) {
  if ($e->getMessage() !== "Could not open ${remote_url}") {
    echo 'FAILED: wrong sha1::salt case'; exit;
  }
}

echo 'OK';
