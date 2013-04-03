<?php 

namespace Simbiotica\CartoDBClient;

use Eher\OAuth\Token;

interface TokenStorageInterface
{
    function getToken();
    
    function setToken(Token $token);
}
?>