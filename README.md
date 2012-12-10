ExliteRPC library
=================

Extreme light and sweet PHP's RPC library inspired by Objective-C Distributed Object (PDO) framework.

Server side example
-------------------

```php
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
```

Client side example
-------------------

```php
<?php
// client.php
$EXLITERPC_SAFE_CLASSES = array('DateTime', 'Person');

require_once('exliterpc.php');

$proxy = new ExliteRPC('http://localhost/service.php', 10, 'sha1salt');
echo $proxy->hello();
echo $proxy->eko('ok');
```

About exliterpc-lite.php
------------------------

This is more light version missing sha1 verifing and signaturing features.

License
-------

>>> ExliteRPC is Copyright 2011 HoopCHINA, Co., Ltd.

    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at
    
       http://www.apache.org/licenses/LICENSE-2.0
    
    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.

Credit
------

Thanks to HoopCHINA.com for sponsor resources to make this library happen.
