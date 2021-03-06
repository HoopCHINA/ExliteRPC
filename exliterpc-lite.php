<?php
/* Copyright 2011 HoopCHINA, Co., Ltd.

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

class ExliteRPC_Lite_Exception extends Exception {};
class ExliteRPC_Lite_NetworkException extends ExliteRPC_Lite_Exception {};
class ExliteRPC_Lite_ProtocolException extends ExliteRPC_Lite_Exception {};

class ExliteRPC_Lite
{
  private $remote_url;
  private $timeout;

  public function __construct($url, $timout = 0) {
    $this->remote_url = $url;
    $this->timeout = $timout;
  }

  public function __call($name, $arguments) {
    array_unshift($arguments, $name);

    $data = @serialize($arguments);
    $data = $this->__rpc($data);

    if (empty($data)) {
      throw new ExliteRPC_Lite_ProtocolException('Response payload is empty');
    }

    return @unserialize($data);
  }

  private function __rpc($data) {
    $matches = parse_url($this->remote_url);

    if (isset($matches['host']))
      $host = $matches['host'];
    else {
      throw new ExliteRPC_Lite_NetworkException('Invalid url format of '.$this->remote_url);
    }

    $headers = array('Host: '.$host,
                     'Accept: php/x-serialize',
                     'User-Agent: Exlite-1.0',
                     'Content-Type: php/x-serialize',
                     'Content-Length: '.strlen($data));

    $options = array('method' => 'POST',
                     'header' => implode("\r\n", $headers),
                     'max_redirects' => 1,
                     'content' => $data);

    if ($this->timeout > 0)
      $options['timeout'] = $this->timeout;

    $context = stream_context_create(array('http' => $options));
    $handle = @fopen($this->remote_url, 'r', false, $context);

    // Connect failed?
    if ($handle === FALSE) {
      throw new ExliteRPC_Lite_NetworkException('Could not open '.$this->remote_url);
    }

    $data = @stream_get_contents($handle);

    // Read failed?
    if ($data === FALSE) {
      $mds = stream_get_meta_data($handle);
      if ($mds['timed_out']) {
        throw new ExliteRPC_Lite_NetworkException('Timed out reading from '.$this->remote_url);
      } else {
        throw new ExliteRPC_Lite_NetworkException('Could not read from '.$this->remote_url);
      }
    }

    return $data;
  }
}

class ExliteRPC_Lite_Server
{
  private $instance;
  private $timeout;

  public function __construct($instance, $timout = 0) {
    $this->instance = $instance;
    $this->timeout = $timout;
  }

  public function handle() {
    $input = @fopen('php://input', 'r');

    if ($input === FALSE) {
      throw new ExliteRPC_Lite_NetworkException('Could not open php://input');
    }
    if ($this->timeout > 0) {
      stream_set_timeout($input, $this->timeout);
    }

    $data = @stream_get_contents($input);

    // Read failed?
    if ($data === FALSE) {
      $mds = stream_get_meta_data($input);
      if ($mds['timed_out']) {
        throw new ExliteRPC_Lite_NetworkException('Timed out reading from php://input');
      } else {
        throw new ExliteRPC_Lite_NetworkException('Could not read from php://input');
      }
    }

    $data = $this->__stub($data);

    // Set header of Content-Type
    header('Content-Type: php/x-serialize');

    $output = @fopen('php://output', 'w');

    if ($output === FALSE) {
      throw new ExliteRPC_Lite_NetworkException('Could not open php://output');
    }
    if ($this->timeout > 0) {
      stream_set_timeout($output, $this->timeout);
    }

    while (strlen($data) > 0) {
      $got = @fwrite($output, $data);
      if ($got === 0 || $got === FALSE) {
        throw new ExliteRPC_Lite_NetworkException('Could not write to php://output');
      }
      $data = substr($data, $got);
    }
  }

  private function __stub($data) {
    if (empty($data)) {
      throw new ExliteRPC_Lite_ProtocolException('Request payload is empty');
    }

    $arguments = @unserialize($data);
    $name = array_shift($arguments);

    if (method_exists($this->instance, $name) && $name[0] != '_') {
      $result = call_user_func_array(array($this->instance, $name), $arguments);
    } elseif (method_exists($this->instance, '__call')) {
      $result = $this->instance->__call($name, $arguments);
    } else {
      throw new ExliteRPC_Lite_ProtocolException('Invalid method invoked of '.$name);
    }

    $data = @serialize($result);

    return $data;
  }
}
