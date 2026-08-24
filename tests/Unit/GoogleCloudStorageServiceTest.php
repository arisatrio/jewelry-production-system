<?php

use App\Support\GoogleCloudStorageService;
use Tests\TestCase;

uses(TestCase::class);

test('gcs credentials path resolves under storage directory', function () {
    config(['gcs.credentials' => 'app/private/gcs-credentials.json']);

    $service = app(GoogleCloudStorageService::class);
    $method = new ReflectionMethod($service, 'credentialsPath');

    expect($method->invoke($service))->toBe(
        storage_path('app/private/gcs-credentials.json'),
    );
});

test('gcs credentials path keeps absolute path as-is', function () {
    config(['gcs.credentials' => '/var/secrets/gcs.json']);

    $service = app(GoogleCloudStorageService::class);
    $method = new ReflectionMethod($service, 'credentialsPath');

    expect($method->invoke($service))->toBe('/var/secrets/gcs.json');
});
