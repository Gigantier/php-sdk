<?php

namespace Gigantier\SDK\Tests;

use Gigantier\Config;
use Gigantier\Client;
use Gigantier\Utils\Constants;
use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\ResponseStack;
use donatj\MockWebServer\Response;
use PHPUnit\Framework\TestCase;


class GigantierTest extends TestCase {

  const ACCESS_TOKEN = 'SOME_ACCESS_TOKEN';
  const ANOTHER_ACCESS_TOKEN = 'ANOTHER_ACCESS_TOKEN';
  const REFRESH_TOKEN = 'SOME_REFRESH_TOKEN';
  const ANOTHER_REFRESH_TOKEN = 'ANOTHER_REFRESH_TOKEN';
  const TEST_VERSION = 'testv';
  const TEST_APP = 'TestApp';
  const CLIENT_ID = 'SOME_CLIENT_ID';
  const CLIENT_SECRET = 'SOME_CLIENT_SECRET';
  const SCOPE = 'SOME_SCOPE';
  const USER_NAME = 'John';
  const USER_SURNAME = 'Doe';
  const USER_EMAIL = 'foo@test.com';
  const USER_PWD = '11111111';
  const INVALID_GRANT = 'invalid_grant';
  const AUTH_URI = '/OAuth/token';
  const CATEGORY_URI = '/Category/list';
  const USER_URI = '/User/me';

  protected static $server;
  protected static $config;

  public static function setUpBeforeClass() {
		self::$server = new MockWebServer;
    self::$server->start();
    
    self::$config = new Config();
    self::$config->clientId = self::CLIENT_ID;
    self::$config->clientSecret = self::CLIENT_SECRET;
    self::$config->scope = self::SCOPE;
    self::$config->host = self::$server->getHost() . ":" . self::$server->getPort();
    self::$config->protocol = 'http';
    self::$config->version = self::TEST_VERSION;
    self::$config->application = self::TEST_APP;
  }
  
  public function testAuthenticateOk() {
    $gigantier = new Client(self::$config);
    $storage = $gigantier->getStorage();
    self::resetStorage($storage);

    $uri = '/api/'. self::TEST_VERSION . self::AUTH_URI;

    self::$server->setResponseOfPath($uri, self::buildUserTokenResponse());

    $result = $gigantier->authenticate(self::USER_EMAIL, self::USER_PWD);
    $this->assertSame(self::ACCESS_TOKEN, $result);
    $this->assertSame(self::REFRESH_TOKEN, $storage->getKey(Constants::USER_REFRESH_TOKEN));
    $this->assertSame(self::ACCESS_TOKEN, $storage->getKey(Constants::USER_TOKEN));

    $lastRequest = self::$server->getLastRequest();
    $body = json_decode($lastRequest->getInput());
    
    $this->basicValidation($lastRequest, $uri);
    $this->assertSame(Constants::GRANT_TYPE_USER, $body->grant_type);
    $this->assertSame(self::CLIENT_ID, $body->client_id);
    $this->assertSame(self::CLIENT_SECRET, $body->client_secret);
    $this->assertSame(self::SCOPE, $body->scope);
    $this->assertSame(self::USER_EMAIL, $body->username);
    $this->assertSame(self::USER_PWD, $body->password);
  }
  
  public function testAuthenticateUnauthorized() {
    $gigantier = new Client(self::$config);
    $storage = $gigantier->getStorage();
    self::resetStorage($storage);

    $uri = '/api/'. self::TEST_VERSION . self::AUTH_URI;

    self::$server->setResponseOfPath($uri, self::buildUnauthorizedResponse());

    $result = $gigantier->authenticate(self::USER_EMAIL, self::USER_PWD);
    $this->assertSame(401, $result->getStatusCode());
    $this->assertSame(self::INVALID_GRANT, $result->error());

    $lastRequest = self::$server->getLastRequest();
    $body = json_decode($lastRequest->getInput());
    
    $this->basicValidation($lastRequest, $uri);
    $this->assertSame(Constants::GRANT_TYPE_USER, $body->grant_type);
    $this->assertSame(self::CLIENT_ID, $body->client_id);
    $this->assertSame(self::CLIENT_SECRET, $body->client_secret);
    $this->assertSame(self::SCOPE, $body->scope);
    $this->assertSame(self::USER_EMAIL, $body->username);
    $this->assertSame(self::USER_PWD, $body->password);
  }

  public function testCallOk() {
    $gigantier = new Client(self::$config);
    $storage = $gigantier->getStorage();
    self::resetStorage($storage);

    $authUri = '/api/'. self::TEST_VERSION . self::AUTH_URI;
    $uri = '/api/'. self::TEST_VERSION . self::CATEGORY_URI;

    self::$server->setResponseOfPath($authUri, self::buildTokenResponse());
    self::$server->setResponseOfPath($uri, self::buildCategoryResponse());

    $result = $gigantier->call(self::CATEGORY_URI);
    $this->assertSame(true, $result->ok());
    $this->assertSame(1, $result->getBody()->categories[0]->id);
    $this->assertSame('First Category', $result->getBody()->categories[0]->name);

    $this->assertSame(self::ACCESS_TOKEN, $storage->getKey(Constants::APP_TOKEN));

    $tokenRequest = self::$server->getRequestByOffset(-2);
    $categoryRequest = self::$server->getRequestByOffset(-1);
    $tokenRequestBody = json_decode($tokenRequest->getInput());
    $categoryRequestBody = json_decode($categoryRequest->getInput());
 
    $this->basicValidation($tokenRequest, $authUri);
    $this->assertSame(Constants::GRANT_TYPE_APP, $tokenRequestBody->grant_type);
    $this->assertSame(self::CLIENT_ID, $tokenRequestBody->client_id);
    $this->assertSame(self::CLIENT_SECRET, $tokenRequestBody->client_secret);
    $this->assertSame(self::SCOPE, $tokenRequestBody->scope);

    $this->basicValidation($categoryRequest, $uri);
    $this->assertSame(self::ACCESS_TOKEN, $categoryRequestBody->access_token);
  }

  public function testCallRenewToken() {
    $gigantier = new Client(self::$config);
    $storage = $gigantier->getStorage();
    self::resetStorage($storage);

    $authUri = '/api/'. self::TEST_VERSION . self::AUTH_URI;
    $uri = '/api/'. self::TEST_VERSION . self::CATEGORY_URI;

    self::$server->setResponseOfPath($authUri, 
      new ResponseStack(self::buildTokenResponse(), self::buildAnotherTokenResponse())
    );
    self::$server->setResponseOfPath($uri, 
      new ResponseStack(self::buildUnauthorizedResponse(), self::buildCategoryResponse())
    );

    $result = $gigantier->call(self::CATEGORY_URI);
    $this->assertSame(true, $result->ok());
    $this->assertSame(1, $result->getBody()->categories[0]->id);
    $this->assertSame('First Category', $result->getBody()->categories[0]->name);

    $firstTokenRequest = self::$server->getRequestByOffset(-4);
    $firstCategoryRequest = self::$server->getRequestByOffset(-3);
    $secondTokenRequest = self::$server->getRequestByOffset(-2);
    $secondCategoryRequest = self::$server->getRequestByOffset(-1);

    $firstTokenRequestBody = json_decode($firstTokenRequest->getInput());
    $firstCategoryRequestBody = json_decode($firstCategoryRequest->getInput());
    $secondTokenRequestBody = json_decode($secondTokenRequest->getInput());
    $secondCategoryRequestBody = json_decode($secondCategoryRequest->getInput());

    $this->basicValidation($firstTokenRequest, $authUri);
    $this->assertSame(Constants::GRANT_TYPE_APP, $firstTokenRequestBody->grant_type);
    $this->assertSame(self::CLIENT_ID, $firstTokenRequestBody->client_id);
    $this->assertSame(self::CLIENT_SECRET, $firstTokenRequestBody->client_secret);
    $this->assertSame(self::SCOPE, $firstTokenRequestBody->scope);

    $this->basicValidation($firstCategoryRequest, $uri);
    $this->assertSame(self::ACCESS_TOKEN, $firstCategoryRequestBody->access_token);

    $this->basicValidation($secondTokenRequest, $authUri);
    $this->assertSame(Constants::GRANT_TYPE_APP, $secondTokenRequestBody->grant_type);
    $this->assertSame(self::CLIENT_ID, $secondTokenRequestBody->client_id);
    $this->assertSame(self::CLIENT_SECRET, $secondTokenRequestBody->client_secret);
    $this->assertSame(self::SCOPE, $secondTokenRequestBody->scope);

    $this->basicValidation($secondCategoryRequest, $uri);
    $this->assertSame(self::ANOTHER_ACCESS_TOKEN, $secondCategoryRequestBody->access_token);

    $this->assertSame(self::ANOTHER_ACCESS_TOKEN, $storage->getKey(Constants::APP_TOKEN));
  }

  public function testAuthenticatedCallOk() {
    $gigantier = new Client(self::$config);
    $storage = $gigantier->getStorage();
    self::resetStorage($storage);

    $authUri = '/api/'. self::TEST_VERSION . self::AUTH_URI;
    $uri = '/api/'. self::TEST_VERSION . self::USER_URI;

    self::$server->setResponseOfPath($authUri, self::buildUserTokenResponse());
    self::$server->setResponseOfPath($uri, self::buildUserResponse(self::USER_NAME, self::USER_SURNAME, self::USER_EMAIL));

    $authenticateResult = $gigantier->authenticate(self::USER_EMAIL, self::USER_PWD);
    $this->assertSame(self::ACCESS_TOKEN, $authenticateResult);
    $this->assertSame(self::REFRESH_TOKEN, $storage->getKey(Constants::USER_REFRESH_TOKEN));
    $this->assertSame(self::ACCESS_TOKEN, $storage->getKey(Constants::USER_TOKEN));

    $result = $gigantier->authenticatedCall(self::USER_URI);
    $this->assertSame(true, $result->ok());
    $this->assertSame(1, $result->getBody()->id);
    $this->assertSame(self::USER_NAME, $result->getBody()->name);

    $this->assertSame(self::ACCESS_TOKEN, $storage->getKey(Constants::USER_TOKEN));

    $tokenRequest = self::$server->getRequestByOffset(-2);
    $userRequest = self::$server->getRequestByOffset(-1);
    $tokenRequestBody = json_decode($tokenRequest->getInput());
    $userRequestBody = json_decode($userRequest->getInput());
 
    $this->basicValidation($tokenRequest, $authUri);
    $this->assertSame(Constants::GRANT_TYPE_USER, $tokenRequestBody->grant_type);
    $this->assertSame(self::CLIENT_ID, $tokenRequestBody->client_id);
    $this->assertSame(self::CLIENT_SECRET, $tokenRequestBody->client_secret);
    $this->assertSame(self::SCOPE, $tokenRequestBody->scope);

    $this->basicValidation($userRequest, $uri);
    $this->assertSame(self::ACCESS_TOKEN, $userRequestBody->access_token);
  }

  public function testAuthenticatedCallRenewToken() {
    $gigantier = new Client(self::$config);
    $storage = $gigantier->getStorage();
    self::resetStorage($storage);

    $authUri = '/api/'. self::TEST_VERSION . self::AUTH_URI;
    $uri = '/api/'. self::TEST_VERSION . self::USER_URI;

    self::$server->setResponseOfPath($authUri, 
      new ResponseStack(self::buildUserTokenResponse(), self::buildAnotherUserTokenResponse())
    );
    self::$server->setResponseOfPath($uri, 
      new ResponseStack(self::buildUnauthorizedResponse(),
      self::buildUserResponse(self::USER_NAME, self::USER_SURNAME, self::USER_EMAIL))
    );

    $authenticateResult = $gigantier->authenticate(self::USER_EMAIL, self::USER_PWD);
    $this->assertSame(self::ACCESS_TOKEN, $authenticateResult);
    $this->assertSame(self::REFRESH_TOKEN, $storage->getKey(Constants::USER_REFRESH_TOKEN));
    $this->assertSame(self::ACCESS_TOKEN, $storage->getKey(Constants::USER_TOKEN));

    $result = $gigantier->authenticatedCall(self::USER_URI);
    $this->assertSame(true, $result->ok());
    $this->assertSame(1, $result->getBody()->id);
    $this->assertSame(self::USER_NAME, $result->getBody()->name);

    $firstTokenRequest = self::$server->getRequestByOffset(-4);
    $firstUserRequest = self::$server->getRequestByOffset(-3);
    $secondTokenRequest = self::$server->getRequestByOffset(-2);
    $secondUserRequest = self::$server->getRequestByOffset(-1);

    $firstTokenRequestBody = json_decode($firstTokenRequest->getInput());
    $firstUserRequestBody = json_decode($firstUserRequest->getInput());
    $secondTokenRequestBody = json_decode($secondTokenRequest->getInput());
    $secondUserRequestBody = json_decode($secondUserRequest->getInput());

    $this->basicValidation($firstTokenRequest, $authUri);
    $this->assertSame(Constants::GRANT_TYPE_USER, $firstTokenRequestBody->grant_type);
    $this->assertSame(self::CLIENT_ID, $firstTokenRequestBody->client_id);
    $this->assertSame(self::CLIENT_SECRET, $firstTokenRequestBody->client_secret);
    $this->assertSame(self::SCOPE, $firstTokenRequestBody->scope);

    $this->basicValidation($firstUserRequest, $uri);
    $this->assertSame(self::ACCESS_TOKEN, $firstUserRequestBody->access_token);

    $this->basicValidation($secondTokenRequest, $authUri);
    $this->assertSame(Constants::GRANT_TYPE_REFRESH, $secondTokenRequestBody->grant_type);
    $this->assertSame(self::CLIENT_ID, $secondTokenRequestBody->client_id);
    $this->assertSame(self::CLIENT_SECRET, $secondTokenRequestBody->client_secret);
    $this->assertSame(self::SCOPE, $secondTokenRequestBody->scope);

    $this->basicValidation($secondUserRequest, $uri);
    $this->assertSame(self::ANOTHER_ACCESS_TOKEN, $secondUserRequestBody->access_token);

    $this->assertSame(self::ANOTHER_ACCESS_TOKEN, $storage->getKey(Constants::USER_TOKEN));
    $this->assertSame(self::ANOTHER_REFRESH_TOKEN, $storage->getKey(Constants::USER_REFRESH_TOKEN));
  }

  static function tearDownAfterClass() {
		// stopping the web server during tear down allows us to reuse the port for later tests
		self::$server->stop();
  }

  private static function buildTokenResponse() {
    return new Response('{ 
      "ok": true, 
      "access_token": "' . self::ACCESS_TOKEN . '", 
      "expires_in": 3600
    }');
  }

  private static function buildUserTokenResponse() {
    return new Response('{ 
      "ok": true, 
      "access_token": "' . self::ACCESS_TOKEN . '", 
      "expires_in": 3600, 
      "refresh_token": "'. self::REFRESH_TOKEN .'"
    }');
  }
  
  private static function buildAnotherTokenResponse() {
    return new Response('{ 
      "ok": true, 
      "access_token": "' . self::ANOTHER_ACCESS_TOKEN . '", 
      "expires_in": 3600
    }');
  }

  private static function buildAnotherUserTokenResponse() {
    return new Response('{ 
      "ok": true, 
      "access_token": "' . self::ANOTHER_ACCESS_TOKEN . '", 
      "expires_in": 3600,
      "refresh_token": "'. self::ANOTHER_REFRESH_TOKEN .'"
    }');
  }

  private static function buildCategoryResponse() {
    return new Response('{ 
      "ok": true, 
      "categories": [{
        "id": 1, 
        "name": "First Category",
        "description": "This is the first category",
        "active": true,
        "visible": 1
      }]
    }');
  }

  private static function buildUnauthorizedResponse() {
    return new Response('{ 
      "ok": false, 
      "error": "'. self::INVALID_GRANT .'", 
      "error_description": "Invalid username and password combination"
    }', [], 401);
  }

  private static function buildUserResponse($name, $surname, $email) {
    return new Response('{ 
      "ok": true, 
      "id": 1,
      "name": "'. $name .'",
      "surname": "'. $surname .'",
      "email": "'. $email .'"
    }');
  }

  private static function resetStorage($storage) {
    $storage->removeKey(Constants::USER_TOKEN);
    $storage->removeKey(Constants::USER_REFRESH_TOKEN);
    $storage->removeKey(Constants::USER_TOKEN_EXPIRES);
    $storage->removeKey(Constants::APP_TOKEN);
    $storage->removeKey(Constants::APP_TOKEN_EXPIRES);
  }

  private function basicValidation($request, $uri) {
    $headers = $request->getHeaders();
    $this->assertSame(Constants::SDK_LANGUAGE, $headers[Constants::SDK_LANGUAGE_HEADER]);
    $this->assertSame(self::TEST_VERSION, $headers[Constants::SDK_VERSION_HEADER]);
    $this->assertSame(self::TEST_APP, $headers[Constants::SDK_APPLICATION_HEADER]);
    $this->assertSame(self::$config->contentType, $headers['Content-Type']);
    $this->assertSame('POST', $request->getRequestMethod());
    $this->assertSame($uri, $request->getRequestUri());
  }

}
?>