<?php

namespace App\Support;

class SpkItemImageUrl
{
    /**
     * Resolve SPK item image URL from file_name.
     *
     * Legacy rows store a bare filename on GCS (production_image_base_url).
     * New uploads are stored as a filename in bucket system-mahakarya/produksi.
     * Older local paths (spk/{id}/file.jpg) remain readable from /storage.
     */
    public static function fromFileName(?string $fileName): ?string
    {
        if (! filled($fileName)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', (string) $fileName));

        if ($path === '' || $path === '-') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        $path = preg_replace('#^/?storage/#', '', $path) ?? $path;
        $path = ltrim($path, '/');

        if ($path === '' || $path === '.' || $path === '-') {
            return null;
        }

        if (str_contains($path, '/')) {
            return '/storage/'.$path;
        }

        $base = rtrim((string) config('spk.production_image_base_url'), '/').'/';

        return $base.$path;
    }

    /**
     * Resolve foto barang jadi dari nilai kolom spk.foto_brj.
     *
     * Unggahan baru disimpan di produksi/brj/ (nilai DB: brj/{nama}.jpg).
     */
    public static function fromFotoBrj(?string $fileName): ?string
    {
        if (! filled($fileName)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', (string) $fileName));

        if ($path === '' || $path === '-') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'brj/')) {
            $base = rtrim((string) config('spk.production_image_base_url'), '/').'/';

            return $base.$path;
        }

        return self::fromFileName($fileName);
    }
}
