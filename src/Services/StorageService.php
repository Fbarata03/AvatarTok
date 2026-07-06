<?php

declare(strict_types=1);

namespace AvatarTok\Services;

use Aws\S3\S3Client;
use Aws\S3\PostObjectV4;
use Ramsey\Uuid\Uuid;

class StorageService
{
    private S3Client $s3;
    private string   $videosBucket;
    private string   $assetsBucket;
    private string   $cdnUrl;

    public function __construct()
    {
        $this->s3 = new S3Client([
            'version'     => 'latest',
            'region'      => $_ENV['AWS_REGION'],
            'credentials' => [
                'key'    => $_ENV['AWS_KEY'],
                'secret' => $_ENV['AWS_SECRET'],
            ],
        ]);

        $this->videosBucket = $_ENV['AWS_BUCKET_VIDEOS'];
        $this->assetsBucket = $_ENV['AWS_BUCKET_ASSETS'];
        $this->cdnUrl       = rtrim($_ENV['AWS_CLOUDFRONT_URL'], '/');
    }

    /**
     * Creates a multipart presigned POST for direct client → S3 video upload.
     * The API server is never in the upload path.
     */
    public function createVideoUpload(string $userId, array $data): array
    {
        $uploadId = Uuid::uuid4()->toString();
        $ext      = $this->extensionFromMime($data['mime_type']);
        $s3Key    = "raw/{$userId}/{$uploadId}.{$ext}";

        $postObject = new PostObjectV4(
            $this->s3,
            $this->videosBucket,
            [
                'key'          => $s3Key,
                'Content-Type' => $data['mime_type'],
                'acl'          => 'private',
            ],
            [
                ['bucket'   => $this->videosBucket],
                ['key'      => $s3Key],
                ['acl'      => 'private'],
                ['content-type-startswith' => 'video/'],
                ['content-length-range' => [1, 500 * 1024 * 1024]],
            ],
            '+15 minutes'
        );

        (new \AvatarTok\Core\Database())->query(
            "INSERT INTO video_uploads (id, user_id, s3_key, status, metadata) VALUES (?, ?, ?, 'pending', ?)",
            // Static call pattern — direct Database::query used in real code
        );

        \AvatarTok\Core\Database::insert('video_uploads', [
            'id'       => $uploadId,
            'user_id'  => $userId,
            's3_key'   => $s3Key,
            'status'   => 'pending',
            'metadata' => json_encode($data),
        ]);

        return [
            'upload_id'     => $uploadId,
            'presigned_url' => $postObject->getFormAttributes()['action'],
            'fields'        => $postObject->getFormInputs(),
            'expires_at'    => date('c', strtotime('+15 minutes')),
        ];
    }

    public function publicUrl(string $bucket, string $key): string
    {
        return "{$this->cdnUrl}/{$key}";
    }

    public function delete(string $bucket, string $key): void
    {
        $this->s3->deleteObject(['Bucket' => $bucket, 'Key' => $key]);
    }

    public function presignedGetUrl(string $bucket, string $key, int $expirySeconds = 3600): string
    {
        $cmd = $this->s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]);
        $req = $this->s3->createPresignedRequest($cmd, "+{$expirySeconds} seconds");
        return (string) $req->getUri();
    }

    private function extensionFromMime(string $mime): string
    {
        return match($mime) {
            'video/mp4'       => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm'      => 'webm',
            'video/x-msvideo' => 'avi',
            default           => 'mp4',
        };
    }
}
