<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Production;
use App\Support\JewelCadSpkEligibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJewelCadRequestRequest extends FormRequest
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
        $details = collect($this->input('details', []))
            ->map(function (mixed $detail): array {
                $row = is_array($detail) ? $detail : [];
                $prepared = [
                    'spk_id' => isset($row['spk_id']) && $row['spk_id'] !== ''
                        ? (int) $row['spk_id']
                        : null,
                    'material' => filled($row['material'] ?? null)
                        ? trim((string) $row['material'])
                        : null,
                    'gold_weight' => filled($row['gold_weight'] ?? null)
                        ? trim((string) $row['gold_weight'])
                        : null,
                    'jwcad_3d' => filled($row['jwcad_3d'] ?? null)
                        ? trim((string) $row['jwcad_3d'])
                        : null,
                    'qty' => isset($row['qty']) && $row['qty'] !== ''
                        ? (int) $row['qty']
                        : null,
                    'estimation_brj' => filled($row['estimation_brj'] ?? null)
                        ? trim((string) $row['estimation_brj'])
                        : null,
                    'notes' => filled($row['notes'] ?? null)
                        ? trim((string) $row['notes'])
                        : null,
                ];

                if (array_key_exists('stones', $row)) {
                    $prepared['stones'] = collect(is_array($row['stones']) ? $row['stones'] : [])
                        ->map(function (mixed $stone): array {
                            $stoneRow = is_array($stone) ? $stone : [];

                            return [
                                'shape_id' => isset($stoneRow['shape_id']) && $stoneRow['shape_id'] !== ''
                                    ? (int) $stoneRow['shape_id']
                                    : null,
                                'position_id' => isset($stoneRow['position_id']) && $stoneRow['position_id'] !== ''
                                    ? (int) $stoneRow['position_id']
                                    : null,
                                'position_nama' => filled($stoneRow['position_nama'] ?? null)
                                    ? trim((string) $stoneRow['position_nama'])
                                    : null,
                                'pcs' => isset($stoneRow['pcs']) && $stoneRow['pcs'] !== ''
                                    ? (int) $stoneRow['pcs']
                                    : null,
                                'carat_per_pcs' => filled($stoneRow['carat_per_pcs'] ?? null)
                                    ? trim((string) $stoneRow['carat_per_pcs'])
                                    : null,
                                'size' => filled($stoneRow['size'] ?? null)
                                    ? trim((string) $stoneRow['size'])
                                    : null,
                            ];
                        })
                        ->values()
                        ->all();
                }

                return $prepared;
            })
            ->values()
            ->all();

        $this->merge([
            'operator' => $this->filled('operator')
                ? $this->string('operator')->trim()->toString()
                : null,
            'notes' => $this->filled('notes')
                ? $this->string('notes')->trim()->toString()
                : null,
            'details' => $details,
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
            'operator' => [
                'required',
                'string',
                'max:150',
                Rule::exists(Employee::class, 'nama_lengkap')
                    ->where('department_id', Employee::DEPARTMENT_PRODUCTION)
                    ->where('status', Employee::STATUS_ACTIVE)
                    ->where('is_deleted', 0),
            ],
            'trans_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.spk_id' => [
                'required',
                'integer',
                Rule::exists(Production::class, 'row_id')->where(
                    fn ($query) => app(JewelCadSpkEligibility::class)->applySelectableScope($query),
                ),
            ],
            'details.*.material' => ['required', 'string', 'max:100'],
            'details.*.gold_weight' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'details.*.jwcad_3d' => ['nullable', 'string', 'max:100'],
            'details.*.file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'details.*.qty' => ['required', 'integer', 'min:1'],
            'details.*.estimation_brj' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'details.*.notes' => ['nullable', 'string', 'max:255'],
            'details.*.stones' => ['sometimes', 'array'],
            'details.*.stones.*.shape_id' => ['nullable', 'integer'],
            'details.*.stones.*.position_id' => ['nullable', 'integer'],
            'details.*.stones.*.position_nama' => ['nullable', 'string', 'max:100'],
            'details.*.stones.*.pcs' => ['nullable', 'integer', 'min:0'],
            'details.*.stones.*.carat_per_pcs' => ['nullable', 'numeric', 'min:0'],
            'details.*.stones.*.size' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operator.required' => 'Operator JewelCAD wajib dipilih.',
            'operator.exists' => 'Operator JewelCAD tidak valid.',
            'trans_date.required' => 'Tanggal request wajib diisi.',
            'details.required' => 'Minimal harus ada satu detail request.',
            'details.min' => 'Minimal harus ada satu detail request.',
            'details.*.spk_id.required' => 'SPK wajib dipilih.',
            'details.*.spk_id.exists' => 'SPK harus sudah di-approve Manager Produksi dan belum masuk proses produksi.',
            'details.*.material.required' => 'Bahan emas wajib diisi.',
            'details.*.qty.required' => 'Qty wajib diisi.',
            'details.*.qty.min' => 'Qty minimal 1.',
            'details.*.estimation_brj.required' => 'Estimasi BRJ wajib diisi.',
            'details.*.estimation_brj.numeric' => 'Estimasi BRJ harus berupa angka.',
            'details.*.estimation_brj.decimal' => 'Estimasi BRJ maksimal 3 desimal.',
            'details.*.file.mimes' => 'Format gambar harus jpg, jpeg, png, pdf, atau webp.',
            'details.*.file.max' => 'Ukuran gambar maksimal 10 MB.',
        ];
    }
}
