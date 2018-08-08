<?php

  namespace Gigantier;

  class Config {
    public $clientId;
    public $clientSecret;
    public $scope;
    public $host = 'api.gigantier.com';
    public $protocol = 'https';
    public $version = 'v1';
    public $retries = 1;
    public $authUri = '/OAuth/token';
    public $contentType = 'application/json';
    public $application;

    public function buildUrl($uri) {
      return $this->protocol . "://" . $this->host . $this->buildPath($uri);
    }
  
    public function buildPath($uri) {
      $versionPath = ($this->version != null) ? "/" . $this->version : '';
      return '/api' . $versionPath . $uri;
    }
  }

?>