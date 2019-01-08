<?php

namespace App\Helper\AWS;

class S3 {
    static $client = null;
    const DEFAULT_BUCKET = 'voiceep';

    protected static function getClient() {
        if (static::$client === null) {
            static::$client = AWS::getSdk()->createS3();
        }

        return static::$client;
    }

    public static function uploadFile($filepath, $keyPrefix = null, $bucket = S3::DEFAULT_BUCKET) {
        $params = [
				'Bucket' => $bucket,
				'Key' => $keyPrefix . md5(uniqid()),
				'SourceFile' => $filepath
		];
        $response = static::getClient()->putObject($params);
        static::getClient()->waitUntil('ObjectExists', $params);

        return $response;
    }

    public static function uploadBlob($blob, $keyPrefix = null, $bucket = S3::DEFAULT_BUCKET) {
        $params = [
				'Bucket' => $bucket,
				'Key' => $keyPrefix . md5(uniqid()),
				'Body' => $blob
		];
        $response = static::getClient()->putObject($params);
        static::getClient()->waitUntil('ObjectExists', $params);

        return $response;
    }
}
