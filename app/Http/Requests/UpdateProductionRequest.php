<?php

namespace App\Http\Requests;

use App\Models\MsPosition;
use App\Models\MsShape;
use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use App\Support\GoldColorOptions;
use App\Support\SpkService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $skuId = $this->input('sku_id');
        $categoryPrefixId = $this->input('category_prefix_id');

        $stones = $this->input('stones');

        if (is_array($stones)) {
            $stones = collect($stones)
                ->filter(fn ($stone): bool => is_array($stone))
                ->map(function (array $stone): array {
                    return [
                        'shape_id' => filled($stone['shape_id'] ?? null)
                            ? $stone['shape_id']
                            : null,
                        'position_id' => filled($stone['position_id'] ?? null)
                            ? $stone['position_id']
                            : null,
                        'position_nama' => filled($stone['position_nama'] ?? null)
                            ? trim((string) $stone['position_nama'])
                            : null,
                        'pcs' => filled($stone['pcs'] ?? null) ? $stone['pcs'] : null,
                        'carat_per_pcs' => filled($stone['carat_per_pcs'] ?? null)
                            ? $stone['carat_per_pcs']
                            : null,
                        'size' => filled($stone['size'] ?? null)
                            ? str_replace(['×', 'X'], 'x', trim((string) $stone['size']))
                            : null,
                    ];
                })
                ->filter(function (array $stone): bool {
                    return filled($stone['shape_id'])
                        || filled($stone['position_id'])
                        || filled($stone['position_nama'])
                        || filled($stone['pcs'])
                        || filled($stone['carat_per_pcs'])
                        || filled($stone['size']);
                })
                ->values()
                ->all();
        } else {
            $stones = [];
        }

        $this->merge([
            'sku_id' => filled($skuId) ? $skuId : null,
            'category_prefix_id' => filled($categoryPrefixId) ? $categoryPrefixId : null,
            'diameter' => $this->filled('diameter')
                ? $this->string('diameter')->trim()->toString()
                : null,
            'dimensi' => $this->filled('dimensi')
                ? $this->string('dimensi')->trim()->toString()
                : null,
            'ring_size' => $this->filled('ring_size')
                ? $this->string('ring_size')->trim()->toString()
                : null,
            'stones' => $stones,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_date' => ['required', 'date'],
            'work_estimated' => ['required', 'integer', 'min:0', 'max:365'],
            'priority' => ['required', 'string', Rule::in(['YES', 'NO'])],
            'description' => ['required', 'string', 'max:4000'],
            'category_prefix_id' => [
                'required',
                'integer',
                Rule::exists(SkuPrefixCategory::class, 'id')->where(
                    fn ($query) => $query->where('is_active', 1),
                ),
            ],
            'sku_id' => [
                'required',
                'integer',
                Rule::exists(SkuMaster::class, 'id')->where(
                    function ($query): void {
                        $query->where('is_active', 1)
                            ->where(function ($builder): void {
                                $builder->where('is_deleted', 0)->orWhereNull('is_deleted');
                            });

                        if ($this->filled('category_prefix_id')) {
                            $query->where('category_prefix_id', (int) $this->input('category_prefix_id'));
                        }
                    },
                ),
            ],
            'frame_id' => ['nullable'],
            'qty' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', Rule::in(SpkService::UNITS)],
            'diameter_length_ringsize' => ['required', 'string', 'max:100'],
            'gold_weight' => ['required', 'numeric', 'min:0'],
            'gold_color' => ['required', 'string', Rule::in(GoldColorOptions::all())],
            'gold_content' => ['nullable', 'string', 'max:100'],
            'jwcad_3d' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'diameter' => ['nullable', 'string', 'max:100'],
            'dimensi' => ['nullable', 'string', 'max:100'],
            'ring_size' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'stones' => ['nullable', 'array'],
            'stones.*.shape_id' => [
                'nullable',
                'integer',
                Rule::exists(MsShape::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'stones.*.position_id' => [
                'nullable',
                'integer',
                Rule::exists(MsPosition::class, 'id'),
            ],
            'stones.*.position_nama' => [
                'nullable',
                'string',
                'max:255',
            ],
            'stones.*.pcs' => ['nullable', 'integer', 'min:0'],
            'stones.*.carat_per_pcs' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'stones.*.size' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^\d+([.,]\d+)?([xX×]\d+([.,]\d+)?)?$/u',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_date.required' => 'Order date wajib diisi.',
            'work_estimated.required' => 'Work estimated wajib diisi.',
            'work_estimated.integer' => 'Work estimated harus berupa angka hari kerja.',
            'work_estimated.min' => 'Work estimated minimal 0 hari kerja.',
            'priority.required' => 'Prioritas wajib dipilih.',
            'description.required' => 'Description wajib diisi.',
            'category_prefix_id.required' => 'Tipe Item wajib dipilih.',
            'category_prefix_id.exists' => 'Tipe Item tidak valid.',
            'sku_id.required' => 'Product Item wajib dipilih.',
            'sku_id.exists' => 'Product Item tidak valid.',
            'qty.required' => 'Qty wajib diisi.',
            'satuan.required' => 'Satuan wajib dipilih.',
            'satuan.in' => 'Satuan harus Pcs atau Pasang.',
            'diameter_length_ringsize.required' => 'Diameter/Length/Ring size wajib diisi.',
            'gold_weight.required' => 'Gold weight wajib diisi.',
            'gold_color.required' => 'Gold color wajib dipilih.',
            'stones.*.shape_id.exists' => 'Bentuk batu tidak valid.',
            'stones.*.position_id.exists' => 'Posisi batu tidak valid.',
            'stones.*.pcs.integer' => 'Jumlah butir harus berupa angka.',
            'stones.*.carat_per_pcs.numeric' => 'Carat per butir harus berupa angka.',
            'stones.*.size.regex' => 'Ukuran batu harus angka (mm) atau format PxL (contoh: 3.50x2.10).',
        ];
    }
}
