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
use Eher\OAuth;

class PublicConnection extends Connection
{
    /**
     * Constructs CartoDB connection and stores token in session
     * @throws RuntimeException on connection or auth failure
     * 
     * @param Session $session
     * @param unknown $key
     * @param unknown $secret
     * @param unknown $subdomain
     * @param unknown $email
     * @param unknown $password
     */
    
    public function setSession(Session $session) {
        $this->session = $session;
    }

    protected function request($uri, $method = 'GET', $args = array())
    {
        $url = $this->apiUrl . $uri;

        $request = new Request($method, $url, isset($args['params'])?$args['params']:array());
        if (!isset($args['headers']['Accept'])) {
            $args['headers']['Accept'] = 'application/json';
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
        //No access token is needed, so we'll just check if we can access some tables.
        try {
            $payload = $this->getTableNames();
        } catch (\RuntimeException $e) {
            return false;
        }
        
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
}

?>