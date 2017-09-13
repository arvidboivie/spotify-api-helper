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

    public function __construct(
        \PDO $db,
        $clientId,
        $clientSecret,
        $redirectURI
    ) {
        $this->db = $db;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectURI = $redirectURI;
    }

    public function getAuthorizeUrl(array $scopes)
    {
        $session = new Session(
            $this->clientId,
            $this->clientSecret,
            $this->redirectURI
        );

        $authorizeUrl = $session->getAuthorizeUrl([
            'scope' => $scopes
        ]);

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

        // Store access and refresh token
        $this->storeToken($session);
    }

    public function getApiWrapper()
    {
        $tokenStatement = $this->db->prepare(
            "SELECT
            access_token,
            refresh_token,
            expires
            FROM `auth`
            WHERE id = :id"
        );

        $tokenStatement->execute([
            'id' => $this->clientId,
        ]);

        $result = $tokenStatement->fetchObject();

        $accessToken = $result->access_token;

        if (time() > $result->expires) {
            $session = new Session($this->clientId, $this->clientSecret);

            if ($session->refreshAccessToken($result->refresh_token) === false) {
                throw new \ErrorException("Failed to refresh access token");
            }

            $this->updateToken($session);

            $accessToken = $session->getAccessToken();
        }

        $api = new SpotifyWebAPI();

        // Set the access token on the API wrapper
        $api->setAccessToken($accessToken);

        return $api;
    }

    private function updateToken($session)
    {
        $tokenStatement = $this->db->prepare('UPDATE auth
                                            SET access_token= :access_token, expires= :expires
                                            WHERE id = :id');

        $tokenStatement->execute([
            'id' => $session->getClientId(),
            'access_token' => $session->getAccessToken(),
            'expires' => $session->getTokenExpiration(),
        ]);
    }

    private function storeToken($session)
    {
        $tokenStatement = $this->db->prepare('INSERT INTO auth(id, access_token, refresh_token, expires)
                                         VALUES(:id, :access_token, :refresh_token, :expires)
                                         ON DUPLICATE KEY UPDATE
                                         access_token= :access_token,
                                         refresh_token= :refresh_token,
                                         expires= :expires');

        $tokenStatement->execute([
            'id' => $session->getClientId(),
            'access_token' => $session->getAccessToken(),
            'refresh_token' => $session->getRefreshToken(),
            'expires' => $session->getTokenExpiration(),
        ]);
    }
}
