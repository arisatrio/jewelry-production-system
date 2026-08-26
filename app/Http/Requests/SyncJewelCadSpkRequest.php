<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncJewelCadSpkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare incoming values before validation.
     */
    protected function prepareForValidation(): void
    {
        $stones = collect($this->input('stones', []))
            ->map(function (mixed $stone): array {
                $row = is_array($stone) ? $stone : [];

                return [
                    'shape_id' => isset($row['shape_id']) && $row['shape_id'] !== ''
                        ? (int) $row['shape_id']
                        : null,
                    'position_id' => isset($row['position_id']) && $row['position_id'] !== ''
                        ? (int) $row['position_id']
                        : null,
                    'position_nama' => filled($row['position_nama'] ?? null)
                        ? trim((string) $row['position_nama'])
                        : null,
                    'pcs' => isset($row['pcs']) && $row['pcs'] !== ''
                        ? (int) $row['pcs']
                        : null,
                    'carat_per_pcs' => filled($row['carat_per_pcs'] ?? null)
                        ? trim((string) $row['carat_per_pcs'])
                        : null,
                    'size' => filled($row['size'] ?? null)
                        ? trim((string) $row['size'])
                        : null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'gold_weight' => filled($this->input('gold_weight'))
                ? trim((string) $this->input('gold_weight'))
                : null,
            'gold_color' => filled($this->input('gold_color'))
                ? trim((string) $this->input('gold_color'))
                : null,
            'jwcad_3d' => filled($this->input('jwcad_3d'))
                ? trim((string) $this->input('jwcad_3d'))
                : null,
            'estimation_brj' => filled($this->input('estimation_brj'))
                ? trim((string) $this->input('estimation_brj'))
                : null,
            'stones' => $stones,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gold_weight' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'gold_color' => ['required', 'string', 'max:100'],
            'jwcad_3d' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'estimation_brj' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'stones' => ['nullable', 'array'],
            'stones.*.shape_id' => ['nullable', 'integer'],
            'stones.*.position_id' => ['nullable', 'integer'],
            'stones.*.position_nama' => ['nullable', 'string', 'max:100'],
            'stones.*.pcs' => ['nullable', 'integer', 'min:0'],
            'stones.*.carat_per_pcs' => ['nullable', 'numeric', 'min:0'],
            'stones.*.size' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gold_weight.required' => 'Berat emas wajib diisi.',
            'gold_color.required' => 'Bahan emas wajib diisi.',
            'estimation_brj.required' => 'Estimasi BRJ wajib diisi.',
            'estimation_brj.numeric' => 'Estimasi BRJ harus berupa angka.',
            'estimation_brj.decimal' => 'Estimasi BRJ maksimal 3 desimal.',
        ];
    }
}
