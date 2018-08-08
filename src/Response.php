<?php

namespace Gigantier;

class Response {

  /**
   *  @var int the http status code
   */
  private $statusCode;

  /**
   *  @var object the json decoded response body
   */
  private $body;

  /**
   *  @var boolean ok/error indicator
   */
  private $ok;

  /**
   *  @var string request error
   */
  private $error;

  /**
   *  Get the status code
   *
   *  @return int
   */
  public function getStatusCode() {
    return $this->statusCode;
  }

  /**
   *  Set the status code
   *
   *  @param int
   *
   *  @return $this
   */
  public function setStatusCode($statusCode) {
    $this->statusCode = $statusCode;
    return $this;
  }

  /**
   *  Set response body
   *
   *  @param object
   *
   *  @return $this
   */
  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  /**
   *  Get response body
   *
   *  @return array
   */
  public function getBody() {
    return $this->body;
  }

  /**
   *  Set ok indicator
   *
   *  @param object
   *
   *  @return $this
   */
  public function setOk($ok) {
    $this->ok = $ok;
    return $this;
  }

  /**
   *  Get ok indicator
   *
   *  @return boolean
   */
  public function ok() {
    return $this->ok;
  }

  /**
   *  Set request error
   *
   *  @param string
   *
   *  @return $this
   */
  public function setError($error) {
    $this->error = $error;
    return $this;
  }

  /**
   *  Get request error
   *
   *  @return string
   */
  public function error() {
    return $this->error;
  }

  public function isError() {
    return $this->error != null && $this->error != "";
  }

  /**
   * Parse response
   */
  public function parse() {
    if (isset($this->body->ok)) {
      $this->setOk($this->body->ok);
    }
    if (isset($this->body->error)) {
      $this->setError($this->body->error);
    }

    return $this;
  }

}
?>
