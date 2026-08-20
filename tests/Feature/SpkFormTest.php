<?php

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\SpkApprovalService;
use App\Support\SpkService;

test('spk form page is accessible', function () {
    $production = app(SpkService::class)->createStock('system');

    $this->get(route('spk.form', $production->row_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spk/form')
            ->where('production.id', $production->row_id)
            ->where('production.isNew', false)
            ->where('production.spkNo', $production->spk_no)
            ->where('production.spkType', 'Stock')
            ->where('formDocumentNo', 'WHOJ-PRD-FR-001')
            ->where(
                'productionImageBaseUrl',
                'https://storage.googleapis.com/system-mahakarya/produksi/',
            )
            ->has('options.categories')
            ->has('options.skus')
            ->has('options.spkTypes')
            ->has('stones')
            ->has('approvalFooter', 3)
            ->where('approvalFooter.0.title', 'Dibuat Oleh')
            ->where('approvalFooter.0.name', 'system')
            ->where('approvalFooter.1.title', 'Disetujui Oleh')
            ->where('approvalFooter.1.name', '-')
            ->where('approvalFooter.1.date', '-')
            ->where('approvalFooter.2.title', 'Manager Produksi')
            ->where('approvalFooter.2.name', '-')
            ->where('approvalFooter.2.date', '-')
            ->has('approval')
        );

    $production->delete();
});

test('spk form can be saved', function () {
    $production = app(SpkService::class)->createStock('system');
    $category = SkuPrefixCategory::query()->active()->orderBy('id')->first()
        ?? SkuPrefixCategory::query()->create([
            'category' => 'TEST '.fake()->unique()->lexify('????'),
            'prefix' => strtoupper(fake()->unique()->lexify('???')),
            'usage_count' => 0,
            'is_active' => 1,
        ]);
    $sku = SkuMaster::factory()->create([
        'category_prefix_id' => $category->id,
    ]);

    $this->post(route('spk.update', $production->row_id), [
        'order_date' => '2026-08-03', // Senin
        'work_estimated' => 5,
        'priority' => 'YES',
        'description' => 'Form save test',
        'category_prefix_id' => $category->id,
        'sku_id' => $sku->id,
        'qty' => 2,
        'satuan' => 'Pcs',
        'diameter_length_ringsize' => '16',
        'gold_weight' => 3.5,
        'gold_color' => 'Yellow Gold',
        'gold_content' => 'Polish',
        'status_order' => 'NO',
        'notes' => 'Catatan uji',
    ])->assertRedirect(route('spk.show', $production->spk_no));

    $production->refresh();

    expect($production->description)->toBe('Form save test')
        ->and($production->priority)->toBe('YES')
        ->and($production->work_estimated)->toBe(5)
        ->and($production->estimated_delivery_time?->toDateString())->toBe('2026-08-10')
        ->and($production->category_prefix_id)->toBe($category->id)
        ->and($production->item_id)->toBeNull()
        ->and($production->item_name)->toBe($category->category)
        ->and($production->sku_id)->toBe($sku->id)
        ->and($production->frame_id)->toBeNull()
        ->and($production->supplier_id)->toBe(1)
        ->and($production->qty)->toBe(2)
        ->and($production->satuan)->toBe('Pcs')
        ->and($production->gold_color)->toBe('Yellow Gold');

    $production->delete();
    $sku->delete();
});

test('spk form save validates required fields', function () {
    $production = app(SpkService::class)->createStock('system');

    $this->from(route('spk.form', $production->row_id))
        ->post(route('spk.update', $production->row_id), [])
        ->assertRedirect(route('spk.form', $production->row_id))
        ->assertSessionHasErrors([
            'order_date',
            'estimated_delivery_time',
            'priority',
            'description',
            'category_prefix_id',
            'sku_id',
            'qty',
            'satuan',
            'diameter_length_ringsize',
            'gold_weight',
            'gold_color',
        ]);

    $production->delete();
});

test('spk show includes canDelete for draft spk', function () {
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => '',
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(84000, 84999)),
    ]);

    $this->get(route('spk.show', $production))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('approval.canDelete', true)
        );

    $production->delete();
});

test('approved spk cannot be deleted', function () {
    $production = app(SpkService::class)->createStock('system');
    $production->update([
        'status' => SpkApprovalService::STATUS_DONE,
        'spk_no' => sprintf('%s/PRD/%05d', now()->format('Y'), random_int(85000, 85999)),
    ]);

    $this->delete(route('spk.destroy', $production->row_id))
        ->assertForbidden();

    $production->refresh();

    expect($production->is_deleted)->toBe(0);

    $production->delete();
});

test('spk can be soft deleted from form', function () {
    $production = app(SpkService::class)->createStock('system');

    $this->delete(route('spk.destroy', $production->row_id))
        ->assertRedirect(route('spk.index'));

    $production->refresh();

    expect($production->is_deleted)->toBe(1)
        ->and($production->deleted_by)->toBe('system')
        ->and($production->deleted_date)->not->toBeNull();

    $this->get(route('spk.form', $production->row_id))
        ->assertNotFound();

    $production->delete();
});

test('frame selector endpoint returns data', function () {
    $this->getJson(route('spk.select.frames'))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['rowId', 'docNo', 'name', 'itemId'],
            ],
        ]);
});
