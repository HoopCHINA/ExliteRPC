<?php
require_once('exliterpc.php');

$server = new ExliteRPC_Server(new helloworld(), 10, '1234567890');
$server->handle();

class helloworld {
  public function eko($i) {
    return $i;
  }
  public function hello() {
    return 'hello, world!';
  }
}

/*
require_once('exliterpc.php');

$proxy = new ExliteRPC('http://localhost/~soplwang/i.php', 10, '1234567890');
echo $proxy->hello();
echo $proxy->eko('ok');
*/
