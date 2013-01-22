<?php

abstract class HTTP_Request {

  protected $methods = array('GET','POST','PUT','DELETE');

  protected $headers = array(
    'Host' => null,
    'User-Agent' => null,
    'Content-type'=>'application/x-www-form-urlencoded',
    );

  private $request_body_exists = FALSE;
  private $sock;

  public $request = '';
  protected $response;

  protected $host;
  protected $path = '/';
  protected $port = '80';
  protected $method = 'GET';
  protected $params;

  abstract public function doGet($params);
  abstract public function doPost($params);
  abstract public function doPut($params);
  abstract public function doDelete($params);

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

    $method = strtoupper($method);

    if(!in_array($method, $this->methods)){
      $method = 'GET';
    }

    $this->method = $method;

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

    while (!feof($this->sock)) {
      $this->response .= fgets($this->sock,1024);
    }

    $this->response = new HTTP_Response($this->response);

    return fclose($this->sock);
  }

  public function response($part = 'full'){

    if($this->response == false) return false;

    switch($part){

      case 'headers': 
        return $this->response->get_headers();
      break;

      case 'body': 
        return $this->response->get_body();
      break;

      case 'object': 
        return $this->response;
      break;

      case 'full':
      default: 
        return $this->response->get_headers() . "\r\n\r\n" . $this->response->get_body();
      break;
    }
  }
}

class HTTP_Response {

  public $response;
  public $headers;

  public function __construct($response){
    $headers = explode("\r\n", $response);
    $response = explode("\r\n\r\n", $response);

    $this->response['headers'] = $response[0];
    $this->response['body'] = $response[1];

    preg_match("#(?:HTTP)\/(?:[0-9\.]+) ([0-9]+) ([a-zA-Z0-9 -_]+)#", $headers[0] , $status_line_array);
    $this->headers['Status']['line'] = $status_line_array[0];
    $this->headers['Status']['code'] = $status_line_array[1];
    $this->headers['Status']['msg'] = $status_line_array[2];

    foreach($headers as $header){
      preg_match("#([a-zA-Z0-9 -_]+?):(.+)#", $header, $header_array);
      if( !empty($header_array[1]) && !empty($header_array[2])){
        $this->headers[$header_array[1]] = trim($header_array[2]);
      }
    }
  }

  public function get_headers(){
    return $this->response['headers'];
  }

  public function get_body(){
    return $this->response['body'];
  }

  public function headers($name = null){

    if($name == null) return $this->headers;

    return isset($this->headers[$name]) ? $this->headers[$name] : false;
  }

  public function status($type = 'code'){
    switch($type){
      case 'line':
        return $this->headers['Status']['line'];
      break;
      case 'msg':
        return $this->headers['Status']['msg'];
      break;
      case 'code':
      default:
        return $this->headers['Status']['code'];
      break;
    }

  }
}

?>