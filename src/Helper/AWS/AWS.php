<?php

namespace App\Helper\AWS;

use Aws\S3\S3Client;

class AWS {
    static $sdk = null;

    public static function getSdk() {
        if (static::$sdk === null) {
            $config = [
                'region'  => 'us-east-2',
                'version' => 'latest'
            ];

            // Create an SDK class used to share configuration across clients.
            static::$sdk = new \Aws\Sdk($config);
        }

        return static::$sdk;
    }

    public static function checkResponseStatus($response, $errorMessage) {
        $statusCode = $response['@metadata']['statusCode'] ?? null;

        if ($statusCode != 200) {
            throw new \Exception($errorMessage);
        }
    }
}
