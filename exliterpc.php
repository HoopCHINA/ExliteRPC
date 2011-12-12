<?php
class ExliteRPC_Exception extends Exception {};
class ExliteRPC_NetworkException extends ExliteRPC_Exception {};
class ExliteRPC_ProtocolException extends ExliteRPC_Exception {};

// SERIALIZE: Don't allow PHP objects except in $EXLITERPC_SAFE_CLASSES
//
function _exliterpc_verify_serialized_data_safe($data)
{
  global $EXLITERPC_SAFE_CLASSES;

  if (isset($EXLITERPC_SAFE_CLASSES) && is_array($EXLITERPC_SAFE_CLASSES)) {
    $safeclss = array();

    foreach ($EXLITERPC_SAFE_CLASSES as $cls) {
      $safeclss[] = implode(array('O:', strlen($cls), ':"', $cls, '"'));
    }
  }

  while ($data) {
    $parts = explode('s:', $data, 2);
    $search = $parts[0];

    if (strpos($search, 'O:') !== FALSE) {
      if (empty($safeclss)) {
        return FALSE;
      }
      if (strpos(str_ireplace($safeclss, '', $search), 'O:') !== FALSE) {
        return FALSE;
      }
    }

    if (empty($parts[1])) {
      break;
    }

    $data = $parts[1];
    $pos = strpos($data, ':');

    if ($pos === FALSE) {
      return FALSE;
    }

    $len = substr($data, 0, $pos);
    $data = substr($data, $pos+2+$len+2);
  }

  return TRUE;
}

class ExliteRPC {
  private $remote_url;
  private $timeout;
  private $salt_;

  public function __construct($url, $timout = 0, $salt = '') {
    $this->remote_url = $url;
    $this->timeout = $timout;
    $this->salt_ = $salt;
  }

  public function __call($name, $arguments) {
    array_unshift($arguments, $name);

    $arguments = @serialize($arguments);
    $timestamp = time();

    $data = pack('Va*', $timestamp, $arguments);
    $data = sha1($data.$this->salt_, true).$data;

    $data = $this->__rpc($data);

    if (empty($data)) {
      throw new ExliteRPC_ProtocolException('Response payload is empty');
    }

    $signat = substr($data, 0, 20);
    $data = substr($data, 20);

    if (strncmp(sha1($data.$this->salt_, true), $signat, 20)) {
      throw new ExliteRPC_ProtocolException('sha1::salt verify of response failed');
    }

    $xpak = unpack('Vtim/a*result', $data);

    if ($xpak['tim'] != $timestamp+1) {
      throw new ExliteRPC_ProtocolException('Timestamp verify of response failed');
    }
    if (!_exliterpc_verify_serialized_data_safe($xpak['result'])) {
      throw new ExliteRPC_ProtocolException('Serialized data safety verify of response failed');
    }

    return @unserialize($xpak['result']);
  }

  private function __rpc($data) {
    $matches = parse_url($this->remote_url);

    if (isset($matches['host']))
      $host = $matches['host'];
    else {
      throw new ExliteRPC_NetworkException('Invalid url format of '.$this->remote_url);
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
      throw new ExliteRPC_NetworkException('Could not open '.$this->remote_url);
    }

    $data = @stream_get_contents($handle);

    // Read failed?
    if ($data === FALSE) {
      $mds = stream_get_meta_data($handle);
      if ($mds['timed_out']) {
        throw new ExliteRPC_NetworkException('Timed out reading from '.$this->remote_url);
      } else {
        throw new ExliteRPC_NetworkException('Could not read from '.$this->remote_url);
      }
    }

    return $data;
  }
}

class ExliteRPC_Server {
  private $instance;
  private $timeout;
  private $salt_;

  public function __construct($instance, $timout = 0, $salt = '') {
    $this->instance = $instance;
    $this->timeout = $timout;
    $this->salt_ = $salt;
  }

  public function handle() {
    $input = @fopen('php://input', 'r');

    if ($input === FALSE) {
      throw new ExliteRPC_NetworkException('Could not open php://input');
    }
    if ($this->timeout > 0) {
      stream_set_timeout($input, $this->timeout);
    }

    $data = @stream_get_contents($input);

    // Read failed?
    if ($data === FALSE) {
      $mds = stream_get_meta_data($input);
      if ($mds['timed_out']) {
        throw new ExliteRPC_NetworkException('Timed out reading from php://input');
      } else {
        throw new ExliteRPC_NetworkException('Could not read from php://input');
      }
    }

    $data = $this->__stub($data);

    // Set header of Content-Type
    header('Content-Type: php/x-serialize');

    $output = @fopen('php://output', 'w');

    if ($output === FALSE) {
      throw new ExliteRPC_NetworkException('Could not open php://output');
    }
    if ($this->timeout > 0) {
      stream_set_timeout($output, $this->timeout);
    }

    while (strlen($data) > 0) {
      $got = @fwrite($output, $data);
      if ($got === 0 || $got === FALSE) {
        throw new ExliteRPC_NetworkException('Could not write to php://output');
      }
      $data = substr($data, $got);
    }
  }

  private function __stub($data) {
    if (empty($data)) {
      throw new ExliteRPC_ProtocolException('Request payload is empty');
    }

    $signat = substr($data, 0, 20);
    $data = substr($data, 20);

    if (strncmp(sha1($data.$this->salt_, true), $signat, 20)) {
      throw new ExliteRPC_ProtocolException('sha1::salt verify of request failed');
    }

    $xpak = unpack('Vtim/a*args', $data);

    if (abs(time() - $xpak['tim']) >= 3600) {
      throw new ExliteRPC_ProtocolException('Timestamp verify of request failed');
    }
    if (!_exliterpc_verify_serialized_data_safe($xpak['args'])) {
      throw new ExliteRPC_ProtocolException('Serialized data safety verify of request failed');
    }

    $arguments = @unserialize($xpak['args']);
    $name = array_shift($arguments);

    if (method_exists($this->instance, $name) && $name[0] != '_') {
      $result = call_user_func_array(array($this->instance, $name), $arguments);
    } elseif (method_exists($this->instance, '__call')) {
      $result = $this->instance->__call($name, $arguments);
    } else {
      throw new ExliteRPC_ProtocolException('Invalid method invoked of '.$name);
    }

    $result = @serialize($result);

    $data = pack('Va*', $xpak['tim']+1, $result);
    $data = sha1($data.$this->salt_, true).$data;

    return $data;
  }
}
