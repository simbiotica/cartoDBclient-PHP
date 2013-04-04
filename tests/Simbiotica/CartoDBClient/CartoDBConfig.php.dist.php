<?php 

namespace Simbiotica\CartoDBBundle;

class CartoDBConfig
{
    const PRIVATE_CONFIG = array(
            'api_key' => 'your-api-key',
            'consumer_key' => 'your-consumer-key',
            'consumer_secret' => 'your-consumer-secret',
            'subdomain' => 'your-subdomain',
            'email' => 'your-email',
            'password' => 'your-password',
    );

    const PUBLIC_CONFIG = array(
            'subdomain' => 'your-subdomain',
    );
}

?>