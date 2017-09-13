<?php

namespace Boivie\SpotifyApiHelper;

use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

class SpotifyApiHelper
{
    private $db;
    private $clientId;
    private $clientSecret;
    private $redirectURI;
    private $apiUser;

    public function __construct(
        \PDO $db,
        $clientId,
        $clientSecret,
        $redirectURI,
        $apiUser
    ) {
        $this->db = $db;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectURI = $redirectURI;
        $this->apiUser = $apiUser;
    }

    public function getAuthorizeUrl(array $scopes)
    {
        $session = new Session(
            $this->clientId,
            $this->clientSecret,
            $this->redirectURI
        );

        $authorizeUrl = $session->getAuthorizeUrl(array(
            'scope' => $scopes
        ));

        return $authorizeUrl;
    }

    public function getAccessToken($code)
    {
        $session = new Session(
            $this->clientId,
            $this->clientSecret,
            $this->redirectURI
        );

        // Request a access token using the code from Spotify
        $session->requestAccessToken($code);
        $accessToken = $session->getAccessToken();
        $refreshToken = $session->getRefreshToken();
        $expiration = $session->getTokenExpiration();

        // Create API wrapper and set access token
        $api = new SpotifyWebAPI();
        $api->setAccessToken($accessToken);

        // Start using the API!
        $userInfo = $api->me();

        // Store access and refresh token
        $tokenStatement = $this->db->prepare('INSERT INTO auth(username, access_token, refresh_token, expires)
                                         VALUES(:username, :access_token, :refresh_token, :expires)
                                         ON DUPLICATE KEY UPDATE
                                         access_token= :access_token,
                                         refresh_token= :refresh_token,
                                         expires= :expires');

        $tokenStatement->execute([
            'username' => $userInfo->id,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires' => $expiration,
        ]);
    }

    public function getApiWrapper()
    {
        $tokenStatement = $this->db->prepare(
            "SELECT
            access_token,
            refresh_token,
            expires
            FROM `auth`
            WHERE username = '".$this->apiUser."'"
        );

        $tokenStatement->execute();

        $result = $tokenStatement->fetchObject();

        $accessToken = $result->access_token;

        if (time() > $result->expires) {
            $session = new Session($this->clientId, $this->clientSecret);

            $session->refreshAccessToken($result->refresh_token);
            $accessToken = $session->getAccessToken();
        }

        $api = new SpotifyWebAPI();

        // Set the access token on the API wrapper
        $api->setAccessToken($accessToken);

        return $api;
    }
}