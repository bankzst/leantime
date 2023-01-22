<?php

namespace leantime\core;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use kamermans\OAuth2\OAuth2Middleware;
use kamermans\OAuth2\OAuth2Subscriber;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\GrantType\RefreshToken;
use kamermans\OAuth2\GrantType\AuthorizationCode;
use kamermans\OAuth2\GrantType\PasswordCredentials;
use kamermans\OAuth2\GrantType\GrantTypeInterface;

class ApiSession
{
    /**
     * Checks passed credentials to see if they are properly provided
     *
     * @param array $requiredCreds
     * @param array $creds
     * @param array $optionalCreds (optional)
     *
     * @return bool
     */
    private static function checkCreds(
        array $requiredCreds,
        array $creds,
        array $optionalCreds = []
    ): bool {
        if (!empty($optionalCreds)) {
            foreach ($optionalCreds as $optionalCred) {
                if (isset($creds[$optionalCred])) {
                    unset($creds[$optionalCred]);
                }
            }
        }

        if (empty($creds) || !empty(array_diff($requiredCreds, $creds))) {
            return false;
        }

        return true;
    }

    /**
     * Creates a Guzzle Client with an oAuth2 connection
     *
     * @param string $baseUri
     * @param GuzzleHttp\HandlerStack $stack
     * @param array $requestDefualts
     *
     * @return GuzzleHttp\Client
     */
    public static function oAuth2(
        string $baseUri,
        HandlerStack $stack,
        array $requestDefaults = []
    ): Client {
        return new Client([
            'base_uri' => $baseUri,
            'handler' => $stack,
            'auth' => 'oauth',
            ...$requestDefaults
        ]);
    }

    /**
     * Creates a handler for oAuth2 Client
     *
     * @see https://github.com/kamermans/guzzle-oauth2-subscriber
     *
     * @param string $baseUri
     * @param array $creds
     * @param bool $usesRefresh (optional)
     * @param kamermans\OAuth2\GrantType\GrantTypeInterface $customGrantType (optional)
     *
     * @return GuzzleHttp\HandlerStack
     */
    public static function oAuth2Grants(
        string $baseUri,
        array $creds,
        bool $usesRefresh = false,
        GrantTypeInterface $customGrantType = null
    ): HandlerStack {
        $middleware_params = [];

        if ($customGrantType !== null) {
            $requiredCreds = [
                'client_id',
                'client_secret'
            ];
            $optionalCreds = [
                'scope',
                'state',
                'redirect_uri',
                'code'
            ];

            if (!self::checkCreds($requiredCreds, $creds, $optionalCreds)) {
                throw new Error(
                    "oAuth2 credentials were incorrectly provided"
                );
            }

            $client = new Client(['base_uri' => $baseUri]);

            if (in_array('code', $creds)) {
                $middleware_params[] = new AuthorizationCode($client, $creds);
            } elseif (in_array('username', $creds) && in_array('password', $creds)) {
                $middleware_params[] = new PasswordCredentials($client, $creds);
            } else {
                $middleware_params[] = new ClientCredentials($client, $creds);
            }
        } else {
            $middleware_params[] = $customGrantType;
        }

        if ($usesRefresh) {
            $middleware_params[] = new RefreshToken($client, $creds);
        }

        $stack = HandlerStack::create();
        $oauth = new OAuth2Middleware(...$middleware_params);
        $stack->push($oauth);

        return $stack;
    }

    /**
     * Creates a Guzzle Client with an oAuth1 connection
     *
     * @param string $baseUri
     * @param array $creds
     * @param array $requestDefaults (optional)
     *
     * @return GuzzleHttp\Client
     */
    public static function oAuth1(
        string $baseUri,
        array $creds,
        array $requestDefaults = []
    ): Client {
        $requiredCreds = [
            'consumer_key',
            'consumer_secret',
            'token',
            'token_secret'
        ];

        if (!self::checkCreds($requiredCreds, $creds)) {
            throw new Error(
                "oAuth1 credentials must match exactly: ['consumer_key' => ..., 'consumer_secret' => ..., 'token' => ..., 'token_secret' => ...]"
            );
        }

        $stack = HandlerStack::create();
        $middleware = new Oauth1($creds);
        $stack->push($middleware);

        return new Client([
            'base_uri' => $baseUri,
            'handler' => $stack,
            ...$requestDefaults
        ]);
    }

    /**
     * Creates a Guzzle Client with a basic authentication connection
     *
     * @see https://docs.guzzlephp.org/en/latest/request-options.html#auth
     *
     * @param string $baseUri
     * @param array $creds
     * @param array $requestDefaults (optional)
     *
     * @return GuzzleHttp\Client
     */
    public static function basicAuth(
        string $baseUri,
        array $creds,
        array $requestDefaults = []
    ): Client {
        $requiredCreds = [
            'username',
            'password'
        ];

        if (!self::checkCreds($requiredCreds, $creds)) {
            throw new Error(
                "basic auth credentials must match exactly: ['username' => ..., 'password' => ...]"
            );
        }

        return new Client([
            'base_uri' => $baseUri,
            'auth' => $creds,
            ...$requestDefaults
        ]);
    }

    /**
     * Creates a Guzzle Client with a digest connection
     *
     * @see https://docs.guzzlephp.org/en/latest/request-options.html#auth
     *
     * @param string $baseUri
     * @param array $creds
     * @param array $requestDefaults (optional)
     *
     * @return GuzzleHttp\Client
     */
    public static function digest(
        string $baseUri,
        array $creds,
        array $requestDefaults = []
    ): Client {
        $requiredCreds = [
            'username',
            'password',
            'digest'
        ];

        if (!self::checkCreds($requiredCreds, $creds)) {
            throw new Error(
                "basic auth credentials must match exactly: ['username' => ..., 'password' => ..., 'digest' => ...]"
            );
        }

        return new Client([
            'base_uri' => $baseUri,
            'auth' => $creds,
            ...$requestDefaults
        ]);
    }

    /**
     * Creates a Guzzle Client with a ntlm connection
     *
     * @see https://docs.guzzlephp.org/en/latest/request-options.html#auth
     *
     * @param string $baseUri
     * @param array $creds
     * @param array $requestDefaults (optional)
     *
     * @return GuzzleHttp\Client
     */
    public static function ntlm(
        string $baseUri,
        array $creds,
        array $requestDefaults = []
    ): Client {
        $requiredCreds = [
            'username',
            'password',
            'ntlm'
        ];

        if (!self::checkCreds($requiredCreds, $creds)) {
            throw new Error(
                "basic auth credentials must match exactly: ['username' => ..., 'password' => ..., 'ntlm' => ...]"
            );
        }

        return new Client([
            'base_uri' => $baseUri,
            'auth' => $creds,
            ...$requestDefaults
        ]);
    }

    /**
     * Creates a Guzzle Client with a token/apikey connection
     *
     * @param string $baseUri
     * @param array $creds
     * @param array $requestDefaults (optional)
     *
     * @return GuzzleHttp\Client
     */
    public static function bearerToken(
        string $baseUri,
        array $creds,
        array $requestDefaults = []
    ): Client {
        $requiredCreds = ['token'];

        if (!self::checkCreds($requiredCreds, $creds)) {
            throw new Error(
                "bearer token credentials must match exactly: ['token' => ...]"
            );
        }

        return new Client([
            'base_uri' => $baseUri,
            'headers' => ['Authorization' => "Bearer $token"],
            ...$requestDefaults
        ]);
    }
}
