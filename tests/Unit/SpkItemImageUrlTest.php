<?php

use App\Support\SpkItemImageUrl;
use Tests\TestCase;

uses(TestCase::class);

test('empty or placeholder file names resolve to null', function (?string $fileName) {
    expect(SpkItemImageUrl::fromFileName($fileName))->toBeNull();
})->with([null, '', '   ', '-', '/storage/']);

test('absolute urls are returned as is', function () {
    expect(SpkItemImageUrl::fromFileName('https://cdn.example.com/item.jpg'))
        ->toBe('https://cdn.example.com/item.jpg');
});

test('bare file names resolve to the production image bucket', function () {
    config(['spk.production_image_base_url' => 'https://storage.googleapis.com/system-mahakarya/produksi/']);

    expect(SpkItemImageUrl::fromFileName('item-123.jpg'))
        ->toBe('https://storage.googleapis.com/system-mahakarya/produksi/item-123.jpg');
});

test('local paths resolve to public storage', function () {
    expect(SpkItemImageUrl::fromFileName('spk\\10\\file.jpg'))->toBe('/storage/spk/10/file.jpg')
        ->and(SpkItemImageUrl::fromFileName('/storage/spk/10/file.jpg'))->toBe('/storage/spk/10/file.jpg');
});
