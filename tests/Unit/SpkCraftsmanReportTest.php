<?php

use App\Models\Production;
use App\Support\SpkCraftsmanReport;
use Tests\TestCase;

uses(TestCase::class);

test('spk craftsman report builds cards with work duration and shrink', function () {
    $production = Production::query()
        ->notDeleted()
        ->where('spk_no', '2024/PRD/00012')
        ->first()
        ?? Production::query()->notDeleted()->whereNotNull('spk_no')->first();

    expect($production)->not->toBeNull();

    $cards = (new SpkCraftsmanReport)->forProduction($production);

    expect($cards)->toBeArray();

    if ($cards !== []) {
        expect($cards[0])->toHaveKeys([
            'no',
            'craftsmanId',
            'craftsmanName',
            'process',
            'workDuration',
            'workDurationMinutes',
            'sentAt',
            'receivedAt',
            'shrink',
        ]);
    }

    if ($production->spk_no === '2024/PRD/00012') {
        expect($cards)->toHaveCount(5)
            ->and($cards[0]['craftsmanName'])->toBe('Dimas')
            ->and($cards[0]['process'])->toBe('Finishing / Handmade')
            ->and($cards[0]['shrink'])->toBe('0.210')
            ->and($cards[0]['workDurationMinutes'])->toBe(328)
            ->and($cards[0]['workDuration'])->toBe('5 jam 28 menit')
            ->and($cards[1]['process'])->toBe('Poles Rangka')
            ->and($cards[1]['shrink'])->toBe('0.060');
    }
});
