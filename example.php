<?php
// service.php
$EXLITERPC_SAFE_CLASSES = array('DateTime', 'Person');

require_once('exliterpc.php');

$server = new ExliteRPC_Server(new helloworld(), 10, 'sha1salt');
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
$EXLITERPC_SAFE_CLASSES = array('DateTime', 'Person');

require_once('exliterpc.php');

$proxy = new ExliteRPC('http://localhost/service.php', 10, 'sha1salt');
echo $proxy->hello();
echo $proxy->eko('ok');
var_dump($proxy->eko(new DateTime()));
*/
