<?php

namespace App\Services;

use Log;
use File;
use AWS;
use Aws\CommandPool;
use Aws\CommandInterface;
use Aws\ResultInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Validator;

/**
 * A service used to upload/delete files from AWS S3 bucket as well as creating record at HAPI
 *
 * @author Chris Chua
 */
class MediaService
{

    protected $s3Instance;

    protected $s3Bucket;

    protected $ajax;

    protected $storeExtension;

    public function __construct($ajax = false, $storeExtension = true)
    {
        $this->s3Instance = \AWS::createClient('s3');

        $this->s3Bucket = env('AWS_S3_BUCKET');

        $this->ajax = $ajax;

        $this->storeExtension = $storeExtension;
    }

    /**
     * Uploads the file to S3 and delete from storage.
     *
     * @param string $sourceFile The directory to the file
     *
     * @param string $targetKey The key name for the file to be uploaded to the S3 bucket.
     *
     * @param bool $deleteLocal Delete local file if set to true.
     *
     * @return Object [url or errors]
     */
    public function uploadFileToS3($sourceFile, $targetKey, $deleteLocal = true, $contentType = '')
    {
        try {
            $postS3Array = array(
                'Bucket'                => $this->s3Bucket,
                'Key'                   => $targetKey,
                'SourceFile'            => $sourceFile,
                'StorageClass'          => 'REDUCED_REDUNDANCY',
            );
            if($contentType != ''){
                $postS3Array['ContentType'] = $contentType;
            }
            $response = new \stdClass();
            $result = $this->s3Instance->putObject($postS3Array);
            $response->url = $result["ObjectURL"];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            $error = $e->getMessage();
            $xml = simplexml_load_string(substr($error, strrpos($error, '<?xml')));
            $response->errors = $xml->Code . ': ' . $xml->Message;
        }
        if ($deleteLocal) {
            File::delete($sourceFile);
        }

        return $response;
    }
}
