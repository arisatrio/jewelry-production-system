<?php

use Tests\TestCase;

uses(TestCase::class);

test('application timezone defaults to Asia/Jakarta', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
});
