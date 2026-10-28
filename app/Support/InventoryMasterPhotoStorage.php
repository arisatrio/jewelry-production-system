<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class InventoryMasterPhotoStorage
{
    public function __construct(
        private readonly GoogleCloudStorageService $gcs,
    ) {}

    public function storeChainPhoto(UploadedFile $file): string
    {
        return $this->upload($file, (string) config('gcs.inventory_chain_folder', 'inventory/chain'));
    }

    public function storeComponentPhoto(UploadedFile $file): string
    {
        return $this->upload($file, (string) config('gcs.inventory_component_folder', 'inventory/component'));
    }

    private function upload(UploadedFile $file, string $folder): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension()));
        $filename = uniqid('inv_', true).($extension !== '' ? '.'.$extension : '');

        return $this->gcs->uploadFile($file, $folder, $filename);
    }
}
