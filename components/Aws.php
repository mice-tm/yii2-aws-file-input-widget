<?php

namespace micetm\aws\fileinput\components;

use yii\base\Component;
use Aws\Sdk as AwsSdk;
use Aws\S3\PostObjectV4;

class Aws extends Component
{
    /** @var array AWS credentials */
    public $credentials;

    /** @var string AWS region */
    public $region;

    /** @var string AWS version */
    public $version = 'latest';

    /** @var string AWS bucket */
    public $bucket;

    /** @var array extra params */
    public $extra = [];

    /** @var AwsSdk SDK instance */
    protected $aws;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->aws = new AwsSdk(array_merge([
            'credentials' => $this->credentials,
            'region' => $this->region,
            'version' => $this->version,
            'Bucket' => $this->bucket,
        ], $this->extra));
    }

    /**
     * Creates PostObjectV4
     * @param string $fileName
     * @param string $acl
     * @param string $expires
     * @return PostObjectV4
     */
    public function createPostObjectV4($fileName, $acl = 'public-read', $expires = '+1 hours')
    {
        return new PostObjectV4(
            $this->aws->createS3(),
            $this->bucket,
            [
                'acl' => $acl,
                'key' => $fileName,
            ],
            [
                ['acl' => $acl],
                ['bucket' => $this->bucket],
                ['starts-with', '$key', ''],
                ['starts-with', '$Content-Type', '']
            ],
            $expires
        );
    }
}
