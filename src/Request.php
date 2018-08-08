<?php

namespace Gigantier;

use Gigantier\Utils\Constants;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\MultipartStream as MultipartStream;
use GuzzleHttp\Exception\RequestException;

class Request {

  private $httpClient;
  private $method;
  private $url;
  private $headers = [];
  private $body = false;
  private $params = [];
  private $config;

  public function __construct($config)  {
    $this->httpClient = new HttpClient();
    $this->config = $config;
    return $this;
  }

  /**
   *  @param string $method the request method for the call
   *  @return $this
   *  @throws Gigantier\Exceptions\InvalidRequestMethod
   */
  public function setMethod($method) {
    $method = strtoupper(trim($method));
    if (!in_array($method, ['GET','POST','PUT','PATCH','DELETE'])) {
        throw new Exceptions\InvalidRequestMethod;
    }
    $this->method = $method;
    return $this;
  }

  /**
   *  @return string
   */
  public function getMethod() {
    return $this->method;
  }

  /**
   *  @return array
   */
  public function getHeaders() {
    return $this->headers;
  }

  /**
   *  Get a specific header
   *
   *  @param string $name the header name
   *  @return false|string false if the header is not set, otherwise the string value
   */
  private function getHeader($name) {
    if (isset($this->headers[$name])) {
      return $this->headers[$name];
    }
    return false;
  }

  /**
   *  Clear request headers
   *
   *  @return $this
   */
  public function clearHeaders() {
    $this->headers = [];
    return $this;
  }

  /**
   *  Add headers to the request
   *
   *  @param array $headers an array of $name => $value headers to set
   *  @return $this
   */
  public function addHeaders($headers) {
    foreach($headers as $name => $value) {
      $this->addHeader($name, $value);
    }
    return $this;
  }

  /**
   *  Add (over overwrite) a single header to the request
   *
   *  @param string $name the header name
   *  @param string $value the header value
   *  @return $this
   */
  public function addHeader($name, $value) {
    $this->headers[$name] = $value;
    return $this;
  }

  /**
   *  Set the default request headers
   *
   *  @return $this
   */
  private function setDefaultHeaders() {
    $defaultHeaders = [
      'Content-Type' => $this->config->contentType,
      'Accept' => $this->config->contentType,
      Constants::SDK_LANGUAGE_HEADER => Constants::SDK_LANGUAGE,
      Constants::SDK_VERSION_HEADER => $this->config->version
    ];

    if (isset($this->config->application)) {
      $defaultHeaders[Constants::SDK_APPLICATION_HEADER] = $this->config->application;
    }

    foreach($defaultHeaders as $name => $value) {
      if (!$this->getHeader($name)) {
        $this->addHeader($name, $value);
      }
    }

    return $this;
  }

  /**
   *  Set the body of the request
   *
   *  @param array $body
   *  @return $this
   */
  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  /**
   *  Get the request body
   *
   *  @return array
   */
  public function getBody() {
    return $this->body;
  }

  /**
   *  Get the request URL
   *
   *  @return string
   */
  public function getURL() {
    return $this->url;
  }

  /**
   *  Set the request URL
   *
   *  @param string $url the URL this request should hit
   *  @return $this
   */
  public function setURL($url) {
    $this->url = trim($url);
    return $this;
  }

  /**
   *  @return array
   */
  public function getPayload() {
    $payload = [];
    $body = $this->getBody();
    if (!empty($body)) {
      $payload[$this->getBodyKey()] = $body;
    }
    $payload['headers'] = $this->getHeaders();
    $params = $this->getQueryStringParams();
    if (!empty($params)) {
      $payload['query'] = $params;
    }

    return $payload;
  }

  /**
   *  Set the query string params
   *
   *  @param array $params
   *  @return $this
   */
  public function setQueryStringParams($params) {
    $this->params = $params;
    return $this;
  }

  /**
   *  Get the query string params
   *
   *  @return array
   */
  public function getQueryStringParams() {
    return $this->params;
  }

  /**
   *  Get the payload key for the body depending on whether the call is JSON/multipart
   *
   *  @return string
   *  @throws Gigantier\Exceptions\InvalidContentType
   */
  public function getBodyKey() {
    switch($this->getHeader('Content-Type')) {
      case 'application/json':
        return'json';
        break;
      case 'application/x-www-form-urlencoded':
        return 'form_params';
        break;
      default:
        throw new Exceptions\InvalidContentType;
    }
  }

  /**
   *  Execute request
   *
   *  @return $this
   */
  public function exec() {
    $this->setDefaultHeaders();

    $result = null;
    try {
      $result = $this->httpClient->request($this->getMethod(), $this->getURL(), $this->getPayload());
    } catch (RequestException $e) {
      $result = $e->getResponse();
    }

    $body = json_decode($result->getBody());
    $response = new Response();
    return $response->setBody($body)->setStatusCode($result->getStatusCode())->parse();
  }

}
?>
