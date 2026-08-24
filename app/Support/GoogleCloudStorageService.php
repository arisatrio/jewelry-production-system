<?php

namespace App\Support;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
            $pathname = $file->getPathname();

            if ($pathname === '' || ! is_readable($pathname)) {
                throw new RuntimeException('File upload tidak dapat dibaca dari temporary path.');
            }

            $stream = fopen($pathname, 'r');

            if ($stream === false) {
                throw new RuntimeException('Gagal membuka file upload untuk dikirim ke GCS.');
            }

            try {
                $this->bucket()->upload($stream, [
                    'name' => $objectPath,
                    'metadata' => [
                        'contentType' => $file->getMimeType() ?: 'application/octet-stream',
                    ],
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } catch (Throwable $exception) {
            Log::error('GCS upload failed.', [
                'path' => $objectPath,
                'message' => $exception->getMessage(),
                'credentials' => $this->credentialsPath(),
            ]);

            throw new RuntimeException(
                'Gagal mengunggah gambar ke Google Cloud Storage: '.$exception->getMessage(),
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

        $bucket = $storage->bucket($bucketName);

        if (! $bucket->exists()) {
            throw new RuntimeException(
                "Bucket GCS '{$bucketName}' tidak ditemukan atau tidak dapat diakses oleh service account.",
            );
        }

        return $bucket;
    }

    private function credentialsPath(): string
    {
        $configured = (string) config('gcs.credentials');

        // Samakan dengan ERP_WHOJ: path relatif selalu di bawah storage/.
        if ($configured === '') {
            return storage_path('app/private/gcs-credentials.json');
        }

        if (str_starts_with($configured, '/')) {
            return $configured;
        }

        return storage_path($configured);
    }
}
