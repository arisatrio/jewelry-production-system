<?php

namespace App\Support;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleCloudStorageService
{
    /**
     * Upload file ke Google Cloud Storage.
     *
     * @return string Public URL of the uploaded object
     */
    public function uploadFile(UploadedFile $file, string $folder, string $filename): string
    {
        $bucketName = (string) config('gcs.bucket');
        $objectPath = trim($folder, '/').'/'.$this->normalizedFilename($file, $filename);

        try {
            $this->bucket()->upload(
                fopen($file->getPathname(), 'r'),
                [
                    'name' => $objectPath,
                    'metadata' => [
                        'contentType' => $file->getMimeType(),
                    ],
                ],
            );
        } catch (\Throwable $exception) {
            Log::error('GCS upload failed.', [
                'path' => $objectPath,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Gagal mengunggah gambar ke Google Cloud Storage.',
                previous: $exception,
            );
        }

        return "https://storage.googleapis.com/{$bucketName}/{$objectPath}";
    }

    private function normalizedFilename(UploadedFile $file, string $filename): string
    {
        if (pathinfo($filename, PATHINFO_EXTENSION) !== '') {
            return $filename;
        }

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension();

        return $extension ? $filename.'.'.$extension : $filename;
    }

    private function bucket(): Bucket
    {
        $projectId = (string) config('gcs.project_id');
        $bucketName = (string) config('gcs.bucket');
        $credentials = $this->credentialsPath();

        if ($projectId === '' || $bucketName === '') {
            throw new RuntimeException('Konfigurasi GCS (project_id / bucket) belum diisi.');
        }

        if (! is_file($credentials)) {
            throw new RuntimeException("File kredensial GCS tidak ditemukan: {$credentials}");
        }

        $storage = new StorageClient([
            'keyFilePath' => $credentials,
            'projectId' => $projectId,
        ]);

        return $storage->bucket($bucketName);
    }

    private function credentialsPath(): string
    {
        $configured = (string) config('gcs.credentials');

        if ($configured === '') {
            return storage_path('app/private/gcs-credentials.json');
        }

        if (str_starts_with($configured, '/')) {
            return $configured;
        }

        $fromBase = base_path($configured);

        if (is_file($fromBase)) {
            return $fromBase;
        }

        return storage_path($configured);
    }
}
