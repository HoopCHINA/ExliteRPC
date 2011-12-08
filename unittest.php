<?php
require_once('exliterpc.php');

if (isset($_GET['m']) && $_GET['m'] === 'server') {
  $server = new ExliteRPC_Server(new helloworld(), 10, 'md5salt');
  $server->handle();
}
else {
  $remote_url = 'http://localhost'.$_SERVER["SCRIPT_NAME"].'?m=server';

  // Empty request payload
  $resp = @file_get_contents($remote_url);
  if ($resp !== FALSE) {
    echo 'FAILED: empty request case'; exit;
  }

  // Normal requests
  $proxy = new ExliteRPC($remote_url, 10, 'md5salt');
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

  // Nonexist method
  $proxy = new ExliteRPC($remote_url, 10, 'md5salt');
  try {
    $resp = $proxy->nonexist();
    echo 'FAILED: nonexist() case not reach'; exit;
  }
  catch (ExliteRPC_NetworkException $e) {
    if ($e->getMessage() !== "Could not open ${remote_url}") {
      echo 'FAILED: nonexist() case'; exit;
    }
  }

  // Non exist remote url
  $proxy = new ExliteRPC('http://nonexist.uri/', 10, 'md5salt');
  try {
    $resp = $proxy->nonexist();
    echo 'FAILED: nonexist uri case not reach'; exit;
  }
  catch (ExliteRPC_NetworkException $e) {
    if ($e->getMessage() !== "Could not open http://nonexist.uri/") {
      echo 'FAILED: nonexist uri case'; exit;
    }
  }

  // MD5 salt wrong
  $proxy = new ExliteRPC($remote_url, 10, 'md5salt$wrong');
  try {
    $resp = $proxy->hello();
    echo 'FAILED: wrong md5 salt case not reach'; exit;
  }
  catch (ExliteRPC_NetworkException $e) {
    if ($e->getMessage() !== "Could not open ${remote_url}") {
      echo 'FAILED: wrong md5 salt case'; exit;
    }
  }
  
  echo 'OK';
}

class helloworld {
  public function eko($i) {
    return $i;
  }
  public function hello() {
    return 'hello, world!';
  }
}
