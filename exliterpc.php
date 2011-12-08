<?php
class ExliteRPC_Exception extends Exception {};
class ExliteRPC_NetworkException extends ExliteRPC_Exception {};
class ExliteRPC_ProtocolException extends ExliteRPC_Exception {};

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
    $arguments = @serialize($arguments);
    $timestamp = time();

    $data = pack('va*Va*V', strlen($name), $name, strlen($arguments), $arguments, $timestamp);
    $data.= md5($data.$this->salt_, true);

    $data = $this->__rpc($data);

    if (empty($data)) {
      throw new ExliteRPC_ProtocolException('Response payload is empty');
    }
    
    $xpak = array();
    $pos = 0;
    $xpak['rlen'] = current(unpack('V_', substr($data, $pos, 4)));
    $pos += 4;
    $xpak['rval'] = substr($data, $pos, $xpak['rlen']);
    $pos += $xpak['rlen'];
    $xpak['tim'] = current(unpack('V_', substr($data, $pos, 4)));
    $pos += 4;
    $xpak['md5'] = substr($data, $pos, 8);
    
    if (strncmp(md5(substr($data, 0, $pos).$this->salt_, true), $xpak['md5'], 8)) {
      throw new ExliteRPC_ProtocolException('Md5::salt verify of response failed');
    }
    if ($xpak['tim'] != $timestamp+1) {
      throw new ExliteRPC_ProtocolException('Timestamp verify of response failed');
    }
    
    return @unserialize($xpak['rval']);
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

    $xpak = array();
    $pos = 0;
    $xpak['nlen'] = current(unpack('v_', substr($data, $pos, 2)));
    $pos += 2;
    $xpak['name'] = substr($data, $pos, $xpak['nlen']);
    $pos += $xpak['nlen'];
    $xpak['alen'] = current(unpack('V_', substr($data, $pos, 4)));
    $pos += 4;
    $xpak['args'] = substr($data, $pos, $xpak['alen']);
    $pos += $xpak['alen'];
    $xpak['tim'] = current(unpack('V_', substr($data, $pos, 4)));
    $pos += 4;
    $xpak['md5'] = substr($data, $pos, 8);
    
    if (strncmp(md5(substr($data, 0, $pos).$this->salt_, true), $xpak['md5'], 8)) {
      throw new ExliteRPC_ProtocolException('Md5::salt verify of request failed');
    }
    if (abs(time() - $xpak['tim']) >= 3600) {
      throw new ExliteRPC_ProtocolException('Timestamp verify of request failed');
    }

    $name = $xpak['name'];
    $arguments = @unserialize($xpak['args']);
    
    if (method_exists($this->instance, $name) && $name{0} != '_') {
      $result = call_user_func_array(array($this->instance, $name), $arguments);
    } elseif (method_exists($this->instance, '__call')) {
      $result = $this->instance->__call($name, $arguments);
    } else {
      throw new ExliteRPC_ProtocolException('Invalid method invoked of '.$name);
    }
	
    $result = @serialize($result);

    $data = pack('Va*V', strlen($result), $result, $xpak['tim']+1);
    $data.= md5($data.$this->salt_, true);

    return $data;
  }
}
