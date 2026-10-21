<?php

namespace App\Http\Requests;

use App\Models\Production;
use App\Support\DiamondDossierInventory;
use App\Support\SpkAllocationSettingStockValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UpdateSpkAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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
                $production = $this->route('production');

                if (! $production instanceof Production) {
                    return;
                }

                $spkId = (int) $production->row_id;

                app(SpkAllocationSettingStockValidator::class)->validateLines(
                    $this->input('setting_stones', []),
                    $spkId,
                    $validator,
                );

                $this->validateDiamondIds($validator, $spkId);
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

    private function validateDiamondIds(Validator $validator, int $spkId): void
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
        $selectable = $inventory->selectableIdsForSpkAllocation($diamondIds, $spkId);

        if (count($selectable) !== count($diamondIds)) {
            $validator->errors()->add(
                'diamonds',
                'Sebagian Batu Dossier sudah dialokasikan SPK lain. Muat ulang halaman lalu pilih ulang.',
            );
        }
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
}
