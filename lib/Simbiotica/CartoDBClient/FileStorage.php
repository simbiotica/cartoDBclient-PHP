<?php

namespace Simbiotica\CartoDBClient;

use Eher\OAuth\Token;
use Simbiotica\CartoDBClient\TokenStorageInterface;

class FileStorage implements TokenStorageInterface
{
    private $filePath;
    
    function __construct($file)
    {
        $this->filePath = $file;
    }
    
    public function getToken() {
        if (file_exists($this->filePath)) {
            return unserialize(file_get_contents($this->filePath));
        }
        else {
            null;
        }
    }
    
    public function setToken(Token $token) {
        @unlink($this->filePath);
        if ($f = @fopen($this->filePath, 'w')) {
            if (@fwrite($f, serialize($token))) {
                @fclose($f);
            }
        }
    }
}

?>