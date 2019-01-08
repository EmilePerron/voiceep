<?php

namespace App\Helper\AWS;

class CloudFront {
    static $client = null;
    const DOMAIN = 'https://d377lbokg94738.cloudfront.net';

    protected static function getClient() {
        if (static::$client === null) {
            static::$client = AWS::getSdk()->createCloudFront();
        }

        return static::$client;
    }

    protected static function getDistribution($id) {
        return static::getClient()->getDistribution([
            'Id' => $id,
        ]);
    }

    public static function buildUrlFromS3Url($S3Url) {
        $filename = substr($S3Url, strrpos($S3Url, '/') + 1);
        $url = static::DOMAIN . '/' . $filename;
        return $url;
    }
}
