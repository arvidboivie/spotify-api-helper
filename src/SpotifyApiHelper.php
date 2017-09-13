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

        // Create API wrapper and set access token
        $api = new SpotifyWebAPI();
        $api->setAccessToken($accessToken);

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
            WHERE id = '".$this->clientId."'"
        );

        $tokenStatement->execute();

        $result = $tokenStatement->fetchObject();

        $accessToken = $result->access_token;

        if (time() > $result->expires) {
            $session = new Session($this->clientId, $this->clientSecret);

            if ($session->refreshAccessToken($result->refresh_token) === false) {
                throw new \ErrorException("Failed to refresh access token");
            }

            $this->storeToken($session);

            $accessToken = $session->getAccessToken();
        }

        $api = new SpotifyWebAPI();

        // Set the access token on the API wrapper
        $api->setAccessToken($accessToken);

        return $api;
    }

    private function storeToken($session)
    {
        $tokenStatement = $this->db->prepare('INSERT INTO auth(id, access_token, refresh_token, expires)
                                         VALUES(:id, :access_token, :refresh_token, :expires)
                                         ON DUPLICATE KEY UPDATE
                                         access_token= :access_token,
                                         refresh_token= :refresh_token,
                                         expires= :expires');

        $tokenStatement->bindParam(':id', $session->getClientId());
        $tokenStatement->bindParam(':access_token', $session->getAccessToken());
        $tokenStatement->bindParam(':expires', $session->getTokenExpiration());

        if ($session->getRefreshToken !== null) {
            $tokenStatement->bindParam(':refresh_token', $session->getRefreshToken());
        }

        $tokenStatement->execute();
    }
}
