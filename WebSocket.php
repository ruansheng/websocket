<?php
// Usage: $master=new WebSocket("localhost",12345);
class WebSocket{
  var $master;
  var $sockets = array();
  var $users   = array();
  var $debug   = false;
 
  function __construct($address,$port){
    error_reporting(E_ALL);
    set_time_limit(0);
    ob_implicit_flush();
    $this->master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP)     or die("socket_create() failed");
    socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1)  or die("socket_option() failed");
    socket_bind($this->master, $address, $port)                    or die("socket_bind() failed");
    socket_listen($this->master,20)                                or die("socket_listen() failed");
    $this->sockets[] = $this->master;
    
	$this->say("Server Started : ".date('Y-m-d H:i:s'));
    $this->say("Listening on   : ".$address." port ".$port);
    $this->say("Master socket  : ".$this->master.PHP_EOL);
    
	while(true){
      $changed = $this->sockets;
      socket_select($changed,$write,$except,0);
      foreach($changed as $socket){
        if($socket==$this->master){
          	$client=socket_accept($this->master);
          	if($client<0){ 
				continue; 
			}else{ 
				$this->connect($client); 
			}
        }else{
          $bytes = @socket_recv($socket,$buffer,2048,0);
          if($bytes==0){ 
			  $this->disconnect($socket); 
		  }else{
            $user = $this->getuserbysocket($socket);
            if(!$user->handshake){ 
				$this->dohandshake($user,$buffer); 
			}else{
				//echo base_convert($buffer,2,10).PHP_EOL;
				echo $this->decode($buffer);
				$this->process($user,$this->decode($buffer)); 
			}
          }
        }
      }
    }
  }
  
  function process($user,$msg){
    $this->send($user->socket,$msg);
  }
  
  function send($client,$msg){
    $this->say("> ".$msg);
    $msg = $this->wrap($msg);
    socket_write($client,$msg,strlen($msg));
    $this->say("! ".strlen($msg));
  }
  
  function connect($socket){
    $user = new User();
    $user->id = uniqid();
    $user->socket = $socket;
    array_push($this->users,$user);
    array_push($this->sockets,$socket);
  }
  
  function disconnect($socket){
    $found=null;
    $n=count($this->users);
    for($i=0;$i<$n;$i++){
      if($this->users[$i]->socket==$socket){ 
		  $found=$i; break; 
	  }
    }
    if(!is_null($found)){
		array_splice($this->users,$found,1); 
	}
    $index=array_search($socket,$this->sockets);
    socket_close($socket);
    if($index>=0){ 
		array_splice($this->sockets,$index,1); 
	}
  }
  
  function dohandshake($user,$buffer){
	list($resource,$host,$origin,$key,$l8b) = $this->getheaders($buffer);
    $upgrade  = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n" .
                "Upgrade: WebSocket\r\n" .
                "Connection: Upgrade\r\n" .               
                "Sec-WebSocket-Origin: " . $origin . "\r\n" .
                "Sec-WebSocket-Location: ws://" . $host . $resource . "\r\n" .
			    "Sec-WebSocket-Accept: " . $this->encry($key) . "\r\n" . "\r\n";                       
    socket_write($user->socket,$upgrade.chr(0),strlen($upgrade.chr(0)));
    $user->handshake=true;
    return true;
  }
  
  function encry($key){
      $mask = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
      return base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
  }
  
 
  function getheaders($req){
    $r=$h=$o=null;
    if(preg_match("/GET (.*) HTTP/"               ,$req,$match)){ 
		$r=$match[1]; 
	}
    if(preg_match("/Host: (.*)\r\n/"              ,$req,$match)){ 
		$h=$match[1]; 
	}
    if(preg_match("/Origin: (.*)\r\n/"            ,$req,$match)){ 
		$o=$match[1]; 
	}
    if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$req,$match)){ 
		$sk=$match[1];
	}
    if($match=substr($req,-8)) { 
		$l8b=$match;
	}
    return array($r,$h,$o,$sk,$l8b);
  }
  
  function getuserbysocket($socket){
    $found=null;
    foreach($this->users as $user){
      if($user->socket==$socket){ 
		  $found=$user; break; 
	  }
    }
    return $found;
  }
  
  function say($msg=""){ 
	  echo $msg; 
  }
  
  function wrap($msg=""){ 
	  return chr(0).$msg.chr(255); 
  }
  
  function decode($buffer)  {
      $len = $masks = $data = $decoded = null;
      $len = ord($buffer[1]) & 127;
      if ($len === 126)  {
          $masks = substr($buffer, 4, 4);
          $data = substr($buffer, 8);
      } else if ($len === 127)  {
          $masks = substr($buffer, 10, 4);
          $data = substr($buffer, 14);
      } else  {
          $masks = substr($buffer, 2, 4);
          $data = substr($buffer, 6);
      }
      for ($index = 0; $index < strlen($data); $index++) {
          $decoded .= $data[$index] ^ $masks[$index % 4];
      }
      return $decoded;
  }
  
}

class User{
  var $id;
  var $socket;
  var $handshake;
}
