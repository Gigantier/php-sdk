<?php

  namespace Gigantier;

  use Gigantier\Utils\Storage as Storage;
  use Gigantier\Utils\Constants as Constants;

  class Client {

    private $config;
    private $storage;

    /**
     *  Create an instance of the SDK
     * 
     *  @param array intitial config
     *
     *  @return $this
     */
    public function __construct($config) {
      $this->config = $config;
      $this->storage = new Storage();
    }

    /**
     * Authenticate user
     * 
     * @param string user email
     * @param string user password
     * 
     * @return string token if ok, Error if error
     */
    public function authenticate(string $identifier, string $pwd) {
      $body = array('username' => $identifier, 'password' => $pwd);
      $response = $this->retrieveToken(Constants::GRANT_TYPE_USER, $body);
      if ($response->getStatusCode() == 200 && $response->ok()) {
        $this->onUserCredential($response);
        return $response->getBody()->access_token;
      } else {
        $this->resetUserToken();
        return $response;
      }
    }

    /**
     * Executes api call
     * 
     * @param string uri
     * @param array body params if necesary
     * 
     * @return Response if ok, Error if error
     */
    public function call(string $uri, array $body = null) {
      $token = $this->getAppToken();
      if ($token instanceof Error) {
        return $token;
      } else {
        $requestBody = array('access_token' => $token);
        if ($body != null && count($body) > 0) {
          $requestBody = array_merge($requestBody, $body);
        }
        return $this->execPost($uri, $requestBody, false);
      }
    }

    /**
     * Executes authenticated user api call
     * 
     * @param string uri
     * @param array body params if necesary
     * 
     * @return Response if ok, Error if error
     */
    public function authenticatedCall(string $uri, array $body = null) {
      $token = $this->getUserToken();
      if ($token instanceof Error) {
        return $token;
      } else {
        $requestBody = array('access_token' => $token);
        if ($body != null && count($body) > 0) {
          $requestBody = array_merge($requestBody, $body);
        }
        return $this->execPost($uri, $requestBody, true);
      }
    }

    public function getStorage() {
      return $this->storage;
    }

    private function execPost($uri, $body, $isUserApi = false, $retries = 1) {
      $request = new Request($this->config);
      $request->setMethod('POST');
      $request->setURL($this->config->buildUrl($uri));
      $request->setBody($body);

      $response = $request->exec();
      if ($response->getStatusCode() == 200 && $response->ok()) {
        return $response;
      } else if ($response->getStatusCode() == 401 && $retries > 0) {
        $token = ($isUserApi) ? $this->getUserToken(true) : $this->getAppToken(true);
        if ($token instanceof Error) {
          return $token;
        } else {
          $body['access_token'] = $token;
          return $this->execPost($uri, $body, $isUserApi, $retries - 1);
        }
      } else {
        return $response;
      }
    }

    private function getAppToken($renew = false) {
      $cachedToken = $this->storage->getKey(Constants::APP_TOKEN);
      if (!$renew && $cachedToken != null && !$this->appTokenExpired()) {
        return $cachedToken;
      } else {
        $response = $this->retrieveToken(Constants::GRANT_TYPE_APP);
        if ($response->getStatusCode() == 200 && $response->ok()) {
          $this->storage->setKey(Constants::APP_TOKEN, $response->getBody()->access_token);
          $this->storage->setKey(Constants::APP_TOKEN_EXPIRES, $this->currentTimeMillis() + $response->getBody()->expires_in);
          return $response->getBody()->access_token;
        } else {
          $this->resetAppToken();
          return $response;
        }
      }
    }

    private function getUserToken($renew = false) {
      $cachedToken = $this->storage->getKey(Constants::USER_TOKEN);
      if (!$renew && $cachedToken != null && !$this->userTokenExpired()) {
        return $cachedToken;
      } else {
        $body = array('refresh_token', $this->storage->getKey(Constants::USER_REFRESH_TOKEN));
        $response = $this->retrieveToken(Constants::GRANT_TYPE_REFRESH, $body);
        if ($response->getStatusCode() == 200 && $response->ok()) {
          $this->onUserCredential($response);
          return $response->getBody()->access_token;
        } else {
          $this->resetUserToken();
          return $response;
        }
      }
    }

    private function retrieveToken(string $grantType, array $body = null) {
      $request = new Request($this->config);
      $request->setMethod('POST');
      $request->setURL($this->config->buildUrl($this->config->authUri));
      
      $requestBody = array(
        'grant_type' => $grantType,
        'client_id' => $this->config->clientId,
        'client_secret' => $this->config->clientSecret,
        'scope' => $this->config->scope
      );

      if ($body != null && count($body) > 0) {
        $requestBody = array_merge($requestBody, $body);
      }

      $request->setBody($requestBody);

      return $request->exec();
    }

    private function appTokenExpired() {
      return $this->currentTimeMillis() > $this->storage->getKey(Constants::APP_TOKEN_EXPIRES);
    }

    private function userTokenExpired() {
      return $this->currentTimeMillis() > $this->storage->getKey(Constants::USER_TOKEN_EXPIRES);
    }

    private function onUserCredential($response) {
      $this->resetUserToken();
      $this->storage->setKey(Constants::USER_TOKEN, $response->getBody()->access_token);
      $this->storage->setKey(Constants::USER_REFRESH_TOKEN, $response->getBody()->refresh_token);
      $this->storage->setKey(Constants::USER_TOKEN_EXPIRES, $this->currentTimeMillis() + $response->getBody()->expires_in);
    }

    private function resetAppToken() {
      $this->storage->removeKey(Constants::APP_TOKEN);
      $this->storage->removeKey(Constants::APP_TOKEN_EXPIRES);
    }

    private function resetUserToken() {
      $this->storage->removeKey(Constants::USER_TOKEN);
      $this->storage->removeKey(Constants::USER_TOKEN_EXPIRES);
      $this->storage->removeKey(Constants::USER_REFRESH_TOKEN);
    }

    private function currentTimeMillis() {
      list($usec, $sec) = explode(" ", microtime());
      return round(((float)$usec + (float)$sec) * 1000);
    }

  }

?>