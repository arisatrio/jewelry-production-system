<?php

namespace App\Http\Requests;

use App\Models\SkuMaster;
use App\Models\SkuPrefixCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignProductionSkuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->input('category_prefix_id');

        return [
            'category_prefix_id' => [
                'required',
                'integer',
                Rule::exists(SkuPrefixCategory::class, 'id')->where('is_active', 1),
            ],
            'sku_id' => [
                'required',
                'integer',
                Rule::exists(SkuMaster::class, 'id')
                    ->where('is_active', 1)
                    ->when(
                        filled($categoryId),
                        fn ($rule) => $rule->where('category_prefix_id', (int) $categoryId),
                    ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_prefix_id.required' => 'Tipe item wajib dipilih.',
            'sku_id.required' => 'SKU wajib dipilih.',
            'category_prefix_id.exists' => 'Tipe item tidak valid.',
            'sku_id.exists' => 'SKU tidak valid untuk tipe item yang dipilih.',
        ];
    }
}
