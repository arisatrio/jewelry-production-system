<?php

namespace App\Services;

class SpkService
{
    public function create(array $data, string $actor): Production {}

    public function createWithDetails(
        array $data, string $actor, ?UploadedFile $file = null
    ): Production {}
}
