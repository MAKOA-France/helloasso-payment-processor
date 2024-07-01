<?php

use Civi\Payment\Exception\PaymentProcessorException;

class CRM_HelloassoPaymentProcessor_HelloAssoClient
{

    // Refresh token will be valid only for 30 days.
    // https://dev.helloasso.com/docs/getting-started
    private const REFRESH_TOKEN_EXP = '30 days';
    private static $instance = null;

    /**
     * @var GuzzleHttp\Client
     */
    protected $guzzleClient;

    private function __construct()
    {
    }


    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleClient(): \GuzzleHttp\Client
    {
        return $this->guzzleClient ?? new \GuzzleHttp\Client();
    }

    /**
     * @param \GuzzleHttp\Client $guzzleClient
     */
    public function setGuzzleClient(\GuzzleHttp\Client $guzzleClient)
    {
        $this->guzzleClient = $guzzleClient;
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getToken($is_test, $oauthUrl, $clientId, $clientSecret)
    {
        if (!Civi::cache('long')->has('helloasso-token' . ($is_test ? '-test' : ''))) {
            $this->accessToken($is_test, $oauthUrl, $clientId, $clientSecret);
        }
        // Check if access_token is expired
        if (self::$instance->isAccessTokenExpired($is_test)) {
            self::$instance->refreshToken($is_test, $oauthUrl, $clientId);
        }
        return Civi::cache('long')->get('helloasso-token' . ($is_test ? '-test' : ''));
    }

    public function invalidateToken($is_test)
    {
        Civi::cache('long')->delete('helloasso-token' . ($is_test ? '-test' : ''));
    }

    private function isAccessTokenExpired($is_test)
    {
        // Get a 30s margin.
        if ((time() + 30) > Civi::cache('long')->get('helloasso-token' . ($is_test ? '-test' : ''))->not_after) {
            return TRUE;
        }
        return FALSE;
    }

    private function accessToken($is_test, $oauthUrl, $clientId, $clientSecret)
    {
        $oauth_response = $this->getGuzzleClient()->request('POST', $oauthUrl, [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret
            ],
            'curl' => [
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
            ],
            'http_errors' => FALSE,
        ]);

        if ($oauth_response->getStatusCode() != 200) {
            Civi::cache('long')->delete('helloasso-token' . ($is_test ? '-test' : ''));
            throw new PaymentProcessorException('HelloAsso: Could not get OAuth token for Payment Processor');
        }
        $token = json_decode($oauth_response->getBody());
        $token->not_after = time() + ($token->expires_in ?? 0);
        Civi::cache('long')->set('helloasso-token' . ($is_test ? '-test' : ''), $token, DateInterval::createFromDateString(self::REFRESH_TOKEN_EXP));
    }

    private function refreshToken($is_test, $oauthUrl, $clientId)
    {
        $oauth_response = $this->getGuzzleClient()->request('POST', $oauthUrl, [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'refresh_token' => Civi::cache('long')->get('helloasso-token' . ($is_test ? '-test' : ''))->refresh_token
            ],
            'curl' => [
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
            ],
            'http_errors' => FALSE,
        ]);

        if ($oauth_response->getStatusCode() != 200) {
            Civi::cache('long')->delete('helloasso-token' . ($is_test ? '-test' : ''));
            throw new PaymentProcessorException('HelloAsso: Could not get OAuth token for Payment Processor');
        }
        $token = json_decode($oauth_response->getBody());
        $token->not_after = time() + ($token->expires_in ?? 0);
        Civi::cache('long')->set('helloasso-token' . ($is_test ? '-test' : ''), $token, DateInterval::createFromDateString(self::REFRESH_TOKEN_EXP));
    }
}