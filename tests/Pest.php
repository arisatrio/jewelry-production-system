<?php

use App\Models\User;
use App\Support\FinishingShrinkAllowanceSettings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

$authenticatedFeatureTests = array_map(
    fn (string $path): string => str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $path),
    [
        ...glob(__DIR__.'/Feature/*.php') ?: [],
        ...glob(__DIR__.'/Feature/Settings/*.php') ?: [],
    ],
);

pest()->beforeEach(function (): void {
    ensureTestingAuthSchema();
    ensureTestingEmployeeSchema();

    $this->actingAs(User::factory()->adminSpk()->create([
        'name' => 'system',
    ]));
})->in(...$authenticatedFeatureTests);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Bootstrap auth tables for sqlite :memory: feature tests.
 * Production auth tables live outside Laravel migrations.
 */
function ensureTestingAuthSchema(): void
{
    if (! Schema::hasTable('role')) {
        Schema::create('role', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_deleted')->default(0);
            $table->string('created_by')->nullable();
            $table->string('modified_by')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('permissions')) {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('module');
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('role_permissions')) {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('user_id')->unique();
            $table->string('email');
            $table->string('password')->nullable();
            $table->string('legacy_password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('spk_role')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}

/**
 * Bootstrap the HR employee table for sqlite :memory: feature tests.
 * Production employee data lives outside Laravel migrations.
 */
function finishingCraftsmanId(): int
{
    $craftsmanId = (int) DB::connection('third')
        ->table('mscraftsman')
        ->where('is_deleted', 0)
        ->orderBy('row_id')
        ->value('row_id');

    return $craftsmanId > 0 ? $craftsmanId : 1;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validFinishingSerahPayload(array $overrides = []): array
{
    return array_merge([
        'process_name' => 'Finishing',
        'craftsman_id' => finishingCraftsmanId(),
        'send_craftsman_date' => now()->format('Y-m-d H:i'),
        'start_weight' => '1.00',
        'item_category' => 'Barang Kecil',
        'work_category' => 'Finishing',
        'work_type' => 'Finishing 1',
        'notes' => 'Catatan serah pengrajin',
        'materials' => [],
    ], $overrides);
}

function finishingShrinkAllowancePercent(
    string $workType = 'Finishing 1',
    string $itemCategory = 'Barang Kecil',
): string {
    return app(FinishingShrinkAllowanceSettings::class)->percentFor($workType, $itemCategory)
        ?? throw new RuntimeException("Shrink allowance is not configured for {$workType} / {$itemCategory}.");
}

function ensureTestingEmployeeSchema(): void
{
    if (Schema::hasTable('employee')) {
        return;
    }

    Schema::create('employee', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('company_id')->nullable();
        $table->unsignedBigInteger('department_id')->nullable();
        $table->unsignedBigInteger('position_id')->nullable();
        $table->unsignedBigInteger('sales_id')->nullable();
        $table->string('nomor_pegawai')->nullable();
        $table->string('nama_lengkap')->nullable();
        $table->string('nama_panggilan')->nullable();
        $table->string('foto')->nullable();
        $table->string('penempatan')->nullable();
        $table->timestamp('tukar_off_eligible_from')->nullable();
        $table->boolean('is_deleted')->default(0);
        $table->string('status')->nullable();
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });
}
