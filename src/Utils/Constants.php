<?php

namespace Gigantier\Utils;

/**
 *  Sdk constants
 */
class Constants {
  public const SDK_LANGUAGE_HEADER = 'X-GIGANTIER-SDK-LANGUAGE';
  public const SDK_VERSION_HEADER = 'X-GIGANTIER-SDK-VERSION';
  public const SDK_APPLICATION_HEADER = 'X-GIGANTIER-SDK-APPLICATION';
  public const SDK_LANGUAGE = 'php';
  public const GRANT_TYPE_APP = 'client_credentials';
  public const GRANT_TYPE_USER = 'password';
  public const GRANT_TYPE_REFRESH = 'refresh_token';

  public const USER_TOKEN = 'user_token';
  public const USER_REFRESH_TOKEN = 'user_refresh_token';
  public const USER_TOKEN_EXPIRES = 'user_token_expires';

  public const APP_TOKEN = 'app_token';
  public const APP_TOKEN_EXPIRES = 'app_token_expires';
}
?>
