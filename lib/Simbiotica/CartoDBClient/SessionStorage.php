<?php

namespace Simbiotica\CartoDBClient;

use Eher\OAuth\Token;
use Simbiotica\CartoDBClient\TokenStorageInterface;

class SessionStorage implements TokenStorageInterface
{
    const SESSION_KEY_SEED = "cartodb";
    
    private $sessionKey;
    
    function __construct($domain)
    {
        session_start();
        $this->sessionKey = SessionStorage::SESSION_KEY_SEED.'-'.$subdomain;
    }
    
    public function getToken() {
        return unserialize($_SESSION[$this->sessionKey]);
    }
    
    public function setToken(Token $token) {
        $_SESSION[$this->sessionKey] = serialize($token);
    }
}

?>