<?php

/**
 * Main connection class. Mostly based on Vizzuality's CartoDB PHP class
 * https://github.com/Vizzuality/cartodbclient-php
 * 
 * @author tiagojsag
 */

namespace Simbiotica\CartoDBClient;

use Eher\OAuth\Request;
use Eher\OAuth\Consumer;
use Eher\OAuth\Token;
use Eher\OAuth\HmacSha1;

class PrivateConnection extends Connection
{
    /**
     * Necessary data to connect to CartoDB
     */
    protected $apiKey;
    protected $consumerKey;
    protected $consumerSecret;
    protected $email;
    protected $password;
    
    protected $oauthUrl;
    
    /**
     * Constructs CartoDB connection and stores token in session
     * @throws RuntimeException on connection or auth failure
     * 
     * @param Session $session
     * @param unknown $consumerKey
     * @param unknown $consumerSecret
     * @param unknown $subdomain
     * @param unknown $email
     * @param unknown $password
     */
    function __construct($storage, $subdomain, $apiKey, $consumerKey, $consumerSecret, $email, $password)
    {
        $this->apiKey = $apiKey;
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->email = $email;
        $this->password = $password;

        $this->oauthUrl = sprintf('https://%s.cartodb.com/oauth/', $this->subdomain);

        parent::__construct($storage, $subdomain);
    }
    
    protected function request($uri, $method = 'GET', $args = array())
    {
        if (!array_key_exists('params', $args))
            $args['params'] = array();
        
        $url = $this->apiUrl . $uri;
        if (!isset($args['headers']['Accept'])) {
            $args['headers']['Accept'] = 'application/json';
        }
        
        if(!empty($this->apiKey))
        {
            $args['params']['api_key'] = $this->apiKey;
            $request = new Request($method, $url, isset($args['params'])?$args['params']:array());
        }
        elseif (!empty($this->consumerKey) && !empty($this->consumerSecret) )
        {
            $sig_method = new HmacSha1();
            $consumer = new Consumer($this->consumerKey, $this->consumerSecret, NULL);
            $token = $this->storage->getToken();

            $request = Request::from_consumer_and_token($consumer, $token,
                    $method, $url, $args['params']);
            $request->sign_request($sig_method, $consumer, $token);
        }
        else {
            throw new \RuntimeException('Need at least one authentication method to access private tables');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request->to_postdata());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);

        $response = array();
        $response['return'] = ($this->json_decode) ? (array) json_decode(
                        curl_exec($ch)) : curl_exec($ch);
        $response['info'] = curl_getinfo($ch);

        curl_close($ch);

        if ($response['info']['http_code'] == 401) {
            $this->authorized = $this->getAccessToken();
            return $this->request($uri, $method, $args);
        }
        
        $payload = new Payload($request);
        $payload->setRawResponse($response);
        return $payload;
    }

    protected function getAccessToken()
    {
        $params = array();
        if(!empty($this->apiKey))
        {
            //for now there is no way to determine if user is authenticated
            //using just the api key, so we assume it is
            return true;
        }
        elseif (!empty($this->consumerKey) && !empty($this->consumerSecret) )
        {
            if(!$this->storage instanceof TokenStorageInterface)
            {
                throw new \RuntimeException('A TokenStorageInterface is needed to use oauth authentication.');
            }
            
            $sig_method = new HmacSha1();
            $consumer = new Consumer($this->consumerKey, $this->consumerSecret, NULL);

            $params = array(
                    'x_auth_username' => $this->email,
                    'x_auth_password' => $this->password,
                    'x_auth_mode' => 'client_auth'
            );

            $request = Request::from_consumer_and_token($consumer, NULL,
                    "POST", $this->oauthUrl . 'access_token', $params);

            $request->sign_request($sig_method, $consumer, NULL);
        }
        else {
            throw new \RuntimeException('Need at least one authentication method to access private tables');
        }
        
        $ch = curl_init($this->oauthUrl . 'access_token');
        curl_setopt($ch, CURLOPT_POST, True);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request->to_postdata());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] != 200) {
            return false;
        }
        //Got the token, now let's store it in session
        $rawTokenData = $this->parse_query($response, true);
        $this->storage->setToken(new Token($rawTokenData['oauth_token'],
                $rawTokenData['oauth_token_secret']));
        
        return true;
    }

    protected function http_parse_headers($header)
    {
        $retVal = array();
        $fields = explode("\r\n",
                preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e',
                        'strtoupper("\0")', strtolower(trim($match[1])));
                if (isset($retVal[$match[1]])) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        return $retVal;
    }

    protected function parse_query($var, $only_params = false)
    {
        /**
         *  Use this function to parse out the query array element from
         *  the output of parse_url().
         */
        if (!$only_params) {
            $var = parse_url($var, PHP_URL_QUERY);
            $var = html_entity_decode($var);
        }

        $var = explode('&', $var);
        $arr = array();

        foreach ($var as $val) {
            $x = explode('=', $val);
            $arr[$x[0]] = $x[1];
        }
        unset($val, $x, $var);
        return $arr;
    }
    
    function importTable($filePath) {
        $url = sprintf('https://%s.cartodb.com/api/v1/imports/', $this->subdomain);
        $params = array();
        $params['file'] = ('@'.realpath($filePath));
        
        $headers = array();
        $headers['Accept'] = 'application/json';
        
        if(!empty($this->apiKey))
        {
            $params['api_key'] = $this->apiKey;
            $request = new Request('POST', $url, $params);
        }
        elseif (!empty($this->consumerKey) && !empty($this->consumerSecret) )
        {
            $sig_method = new HmacSha1();
            $consumer = new Consumer($this->consumerKey, $this->consumerSecret, NULL);
            $token = $this->storage->getToken();

            $request = Request::from_consumer_and_token($consumer, $token,
                    'POST', $url, $params);
            $request->sign_request($sig_method, $consumer, $token);
        }
        else {
            throw new \RuntimeException('Need at least one authentication method to access private tables');
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = array();
        $response['return'] = ($this->json_decode) ? (array) json_decode(
                        curl_exec($ch)) : curl_exec($ch);
        $response['info'] = curl_getinfo($ch);

        curl_close($ch);

        if ($response['info']['http_code'] == 401) {
            $this->authorized = $this->getAccessToken();
            return $this->request($url, 'POST', $params);
        }
        
        $payload = new Payload($request);
        $payload->setRawResponse($response);
        return $payload;
//        
//        
//        //wait for asyncronous response
//        $finished = false;
//        $target_url = 'https://user.cartodb.com/api/v1/imports/' . $result->item_queue_id . '?api_key=key';
//        $status = null;
//        while (!$finished) {
//            $status = json_decode(file_get_contents($target_url, 0, null, null));
//            $finished = $status->success;
//        }
//        var_dump($status);
    }
    function checkImportStatus($importId){

        $url = sprintf('https://%s.cartodb.com/api/v1/imports/%s', $this->subdomain, $importId);
        $params = array();
        
        
        $headers = array();
        $headers['Accept'] = 'application/json';
        
        if(!empty($this->apiKey))
        {
            $params['api_key'] = $this->apiKey;
            $request = new Request('POST', $url, $params);
        }
        elseif (!empty($this->consumerKey) && !empty($this->consumerSecret) )
        {
            $sig_method = new HmacSha1();
            $consumer = new Consumer($this->consumerKey, $this->consumerSecret, NULL);
            $token = $this->storage->getToken();

            $request = Request::from_consumer_and_token($consumer, $token,
                    'GET', $url, $params);
            $request->sign_request($sig_method, $consumer, $token);
        }
        else {
            throw new \RuntimeException('Need at least one authentication method to access private tables');
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = array();
        $response['return'] = ($this->json_decode) ? (array) json_decode(
                        curl_exec($ch)) : curl_exec($ch);
        $response['info'] = curl_getinfo($ch);

        curl_close($ch);

        if ($response['info']['http_code'] == 401) {
            $this->authorized = $this->getAccessToken();
            return $this->request($url, 'POST', $params);
        }
        
        $payload = new Payload($request);
        $payload->setRawResponse($response);
        return $payload;
    }
}

?>