<?php

namespace App\Http\Requests;

use App\Models\Production;
use App\Models\SpkDiamond;
use App\Support\DiamondDossierInventory;
use App\Support\SpkAllocationReservedStock;
use App\Support\SpkAllocationSubsetValidator;
use App\Support\SpkDiamondSettingStockValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StoreSpkDiamondRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $spkId = $this->input('spk_id');

        $this->merge([
            'no_spk_diamond' => filled($this->input('no_spk_diamond'))
                ? trim((string) $this->input('no_spk_diamond'))
                : null,
            'spk_id' => filled($spkId) && (int) $spkId > 0 ? (int) $spkId : null,
            'keterangan' => filled($this->input('keterangan'))
                ? trim((string) $this->input('keterangan'))
                : null,
            'weight_frame' => filled($this->input('weight_frame'))
                ? str_replace(',', '.', trim((string) $this->input('weight_frame')))
                : null,
            'weight_stone' => filled($this->input('weight_stone'))
                ? str_replace(',', '.', trim((string) $this->input('weight_stone')))
                : null,
            'setting_stones' => $this->normalizeStoneLines($this->input('setting_stones', [])),
            'diamonds' => $this->normalizeDiamondLines($this->input('diamonds', [])),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'no_spk_diamond' => ['required', 'string', 'max:100'],
            'spk_id' => [
                'required',
                'integer',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'weight_frame' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'weight_stone' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'setting_stones' => ['nullable', 'array'],
            'setting_stones.*.stone_id' => ['required', 'integer', 'min:1'],
            'setting_stones.*.pcs' => ['nullable', 'integer', 'min:0'],
            'setting_stones.*.crt' => ['nullable', 'numeric', 'min:0'],
            'setting_stones.*.notes' => ['nullable', 'string', 'max:500'],
            'diamonds' => ['nullable', 'array'],
            'diamonds.*.diamond_id' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return array{
     *     setting_stones: list<array<string, mixed>>,
     *     diamonds: list<array<string, mixed>>
     * }
     */
    public function linePayload(): array
    {
        return [
            'setting_stones' => $this->input('setting_stones', []),
            'diamonds' => $this->input('diamonds', []),
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $spkId = (int) ($this->input('spk_id') ?? 0);

                $this->validateStoneIds($validator);
                $this->validateDiamondIds($validator, $spkId);
                app(SpkDiamondSettingStockValidator::class)->validateLines(
                    $this->input('setting_stones', []),
                    $validator,
                    $spkId > 0 ? $spkId : null,
                );
                app(SpkAllocationSubsetValidator::class)->validateForSpk(
                    $spkId,
                    $this->input('setting_stones', []),
                    $this->input('diamonds', []),
                    $validator,
                );
            },
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $toastMessage = $validator->errors()->first('setting_stones')
            ?? $validator->errors()->first('diamonds');

        if ($toastMessage !== null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $toastMessage,
            ]);
        }

        parent::failedValidation($validator);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'no_spk_diamond.required' => 'No SPK Diamond wajib diisi.',
            'spk_id.required' => 'SPK produksi wajib dipilih.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'weight_frame.required' => 'Berat rangka wajib diisi.',
            'weight_stone.required' => 'Berat batu wajib diisi.',
            'setting_stones.*.stone_id.required' => 'Batu setting wajib dipilih.',
            'diamonds.*.diamond_id.required' => 'Batu Dossier wajib dipilih.',
            'diamonds.*.diamond_id.distinct' => 'Batu Dossier tidak boleh dipilih lebih dari sekali.',
        ];
    }

    /**
     * @return list<array{stone_id: int, pcs: string|null, crt: string|null, notes: string|null}>
     */
    private function normalizeStoneLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId <= 0) {
                continue;
            }

            $normalized[] = [
                'stone_id' => $stoneId,
                'pcs' => $this->normalizePcs($line['pcs'] ?? null),
                'crt' => filled($line['crt'] ?? null)
                    ? str_replace(',', '.', trim((string) $line['crt']))
                    : null,
                'notes' => filled($line['notes'] ?? null)
                    ? trim((string) $line['notes'])
                    : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{diamond_id: int}>
     */
    private function normalizeDiamondLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $diamondId = (int) ($line['diamond_id'] ?? 0);

            if ($diamondId <= 0) {
                continue;
            }

            $normalized[] = ['diamond_id' => $diamondId];
        }

        return $normalized;
    }

    private function normalizePcs(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $number = (float) str_replace(',', '.', trim((string) $value));

        if (! is_finite($number)) {
            return null;
        }

        return (string) (int) round($number);
    }

    private function validateStoneIds(Validator $validator): void
    {
        if (! Schema::connection('third')->hasTable('msstone')) {
            return;
        }

        foreach ($this->input('setting_stones', []) as $index => $line) {
            $stoneId = (int) ($line['stone_id'] ?? 0);

            if ($stoneId <= 0) {
                continue;
            }

            $exists = DB::connection('third')
                ->table('msstone')
                ->where('row_id', $stoneId)
                ->where('is_deleted', 0)
                ->exists();

            if (! $exists) {
                $validator->errors()->add(
                    "setting_stones.{$index}.stone_id",
                    'Batu tidak valid.',
                );
            }
        }
    }

    private function validateDiamondIds(Validator $validator, int $spkId = 0): void
    {
        $diamondIds = collect($this->input('diamonds', []))
            ->pluck('diamond_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($diamondIds === []) {
            return;
        }

        $inventory = app(DiamondDossierInventory::class);
        $spkDiamond = $this->route('spkDiamond');
        $spkDiamondId = $spkDiamond instanceof SpkDiamond ? $spkDiamond->id : null;

        $hasAllocation = $spkId > 0
            && app(SpkAllocationReservedStock::class)->hasActiveLinesForSpk($spkId);

        $selectable = $hasAllocation
            ? $inventory->selectableIdsForSpkAllocation($diamondIds, $spkId)
            : $inventory->selectableIdsForMounting($diamondIds, null, $spkDiamondId);

        if (count($selectable) !== count($diamondIds)) {
            $validator->errors()->add(
                'diamonds',
                'Sebagian Batu Dossier sudah dipakai. Muat ulang halaman lalu pilih ulang.',
            );
        }
    }
}
