<?php
// service.php
require_once('exliterpc.php');

$server = new ExliteRPC_Server(new helloworld(), 10, 'md5salt');
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
// client.php
require_once('exliterpc.php');

$proxy = new ExliteRPC('http://localhost/service.php', 10, 'md5salt');
echo $proxy->hello();
echo $proxy->eko('ok');
*/
