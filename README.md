ExliteRPC library
=================

Extreme light and sweet PHP's RPC library inspired by Objective-C Distributed Object (PDO) framework.

Server side example
-------------------

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

Client side example
-------------------

    <?php
    // client.php
    require_once('exliterpc.php');
    
    $proxy = new ExliteRPC('http://localhost/service.php', 10, 'md5salt');
    echo $proxy->hello();
    echo $proxy->eko('ok');

About exliterpc-lite.php
------------------------

This is more light version missing md5 verifing and signaturing features.

Credit
------

Thanks to HoopCHINA.com for sponsor resources to make this library happen.
