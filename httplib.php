<?php

class HTTP_Request {

  protected $headers = array(
    'Host' => null,
    'User-Agent' => null,
    'Content-type'=>'application/x-www-form-urlencoded',
    );

  public $error;

  private $request_body_exists = FALSE;
  private $sock;
  private $request = '';
  private $response;
  private $params;

  protected $host;
  protected $path = '/';
  protected $port = '80';
  protected $method = 'GET';

  public function __construct($resource = null, $method = 'GET'){

    $this->headers['Date'] = date("D, d M Y H:i:s T");

    $this->headers['User-Agent'] = 'PHP Nap/1.0 (PHP ' . phpversion() . ')';

    if(!empty($resource)) $this->load_resource($resource);

    $this->method($method);
  }

  public function load_resource($resource_uri, $params = array()){

    $resource = parse_url($resource_uri);

    $this->host = $resource['host'];

    $this->headers['Host'] = $resource['host'];

    if(!@empty($resource['path'])){
      $this->path = $resource['path'];
    }

    if(!@empty($resource['port'])){
      $this->port = $resource['port'];
    }

    if(!@empty($resource['query'])){
      $this->params = trim($resource['query'], '&');
    }

    if(!@empty($params)){
      $this->params($params);
    }

    //  for method chaining
    return $this;
  }

  public function method($method){

    $this->method = $strtoupper($method);

    // for method chaining
    return $this;
  }

  public function set_headers($headers = array()){
    foreach($headers as $field => $value){
      $this->headers[$field] = $value;
    }

    // for method chaning
    return $this;
  }

  public function params($params = array()){

    if(!empty($this->params)){
      $this->params .= '&' . http_build_query($params);
    } else {
      $this->params = http_build_query($params);
    }
    // for method chaning
    return $this;
  }

  protected function parse_headers($headers = array()){
    foreach($this->headers as $field => $value){
      $this->request .= $field . ": " . $value . "\r\n";
    }
  }

  protected function method_checks(){

    $this->request_body_exists = FALSE;

    switch($this->method){
      case 'GET':
        $this->path .= '?' . $this->params;
      break;
      case 'PUT':
      case 'POST':
      case 'DELETE':
        $this->request_body_exists = TRUE;
        $this->headers['Content-length'] = strlen($this->params);
      break;
    }
  }

  public function send_request(){

    if(empty($this->host)){
      // throw new Exception("No Host specified", 1);
      return false;
    }

    $this->sock = @fsockopen($this->host, $this->port, $errno, $errstr, 30);

    if($this->sock === FALSE){
      // throw new Exception("Cannot establish connection to $this->host:$this->port ($errstr)", $errno);
      $this->error = array($errno, $errstr);
      return false;
    }

    $this->method_checks();

    $this->request .= "{$this->method} {$this->path} HTTP/1.1\r\n";

    $this->parse_headers();

    $this->request .= "Connection: Close\r\n\r\n";

    if($this->request_body_exists === TRUE){
      $this->request .= $this->params;
    }

    fputs($this->sock, $this->request);

    $response_headers = '';
    while (!preg_match("/\\r\\n\\r\\n$/", $response_headers)) {
      $response_headers .= fgets($this->sock,1024);
    }

    $this->response = new HTTP_Response($this->sock, $response_headers);

    return fclose($this->sock);
  }

  public function response($part = 'full'){

    if($this->response == false) return false;

    switch($part){

      case 'h': 
      case 'headers': 
        return $this->response->get_headers();
      break;

      case 'b':
      case 'body':
        return $this->response->get_body();
      break;

      case 'o':
      case 'object':
        return $this->response;
      break;

      case 'f':
      case 'full':
      default: 
        return $this->response->get_headers(TRUE) . "\r\n\r\n" . $this->response->get_body();
      break;
    }
  }
}

class HTTP_Response {

  private $response;
  private $headers;
  private $status;

  public function __construct(&$sock, $headers){

    $headers = explode("\r\n", $headers);
    foreach($headers as $line){
      $this->parse_header($line);
    }

    $this->parse_body($sock);
  }

  public function get_headers($string = FALSE){

    if($string == TRUE){
      $headrs = '';
      foreach($this->headers as $n => $v){ $headrs .= ($n!='status') ? "$n: $v\r\n" : "$v\r\n"; }
      return $headrs;
    }

    return $this->headers;
  }

  public function get_body(){

    return $this->response;
  }

  protected function parse_body(&$sock){
    while (!feof($sock)) {
      $this->response .= fgets($sock,1024);
    }
  }

  protected function parse_header($line){

    if(preg_match("#(?:HTTP)\/(?:[0-9\.]+) ([0-9]+) ([a-zA-Z0-9 -_]+)#", $line , $status_line_array)){
      $this->headers['status'] = $status_line_array[0];
      $this->status['code'] = $status_line_array[1];
      $this->status['msg'] = $status_line_array[2];
    }

    if(preg_match("#([a-zA-Z0-9 -_]+?): (.+)#", $line, $header_array)){
      $this->headers[$header_array[1]] = trim($header_array[2]);
    }
  }

  public function header($name){

    return isset($this->headers[$name]) ? $this->headers[$name] : false;
  }

  public function status($type = 'code'){
    switch($type){
      case 'l':
      case 'line':
        return $this->headers['status'];
      break;

      case 'm':
      case 'msg':
        return $this->status['msg'];
      break;

      case 'c':
      case 'code':
      default:
        return $this->status['code'];
      break;
    }
  }
}

?>