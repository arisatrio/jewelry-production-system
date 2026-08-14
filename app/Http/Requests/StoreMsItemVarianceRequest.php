<?php

namespace App\Http\Requests;

use App\Models\MsItem;
use App\Models\MsItemVariance;
use App\Support\GoldColorOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMsItemVarianceRequest extends FormRequest
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
        $this->merge([
            'gold_weight' => $this->filled('gold_weight') ? $this->input('gold_weight') : null,
            'diameter' => $this->filled('diameter')
                ? $this->string('diameter')->trim()->toString()
                : null,
            'dimensi' => $this->filled('dimensi')
                ? $this->string('dimensi')->trim()->toString()
                : null,
            'ring_size' => $this->filled('ring_size')
                ? $this->string('ring_size')->trim()->toString()
                : null,
            'gold_color' => $this->filled('gold_color')
                ? $this->string('gold_color')->trim()->toString()
                : null,
            'jwcad_3d' => $this->filled('jwcad_3d')
                ? $this->string('jwcad_3d')->trim()->toString()
                : null,
            'description' => $this->filled('description')
                ? $this->string('description')->trim()->toString()
                : null,
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
            'item_id' => [
                'required',
                'integer',
                Rule::exists(MsItem::class, 'row_id')->where(
                    fn ($query) => $query->where('is_deleted', 0),
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(MsItemVariance::class, 'name')->where(
                    fn ($query) => $query
                        ->where('is_deleted', 0)
                        ->where('item_id', $this->integer('item_id')),
                ),
            ],
            'description' => ['nullable', 'string'],
            'diameter' => ['nullable', 'string', 'max:100'],
            'dimensi' => ['nullable', 'string', 'max:100'],
            'ring_size' => ['nullable', 'string', 'max:100'],
            'gold_weight' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'gold_color' => ['required', 'string', Rule::in(GoldColorOptions::all())],
            'jwcad_3d' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_id.required' => 'Tipe item wajib dipilih.',
            'item_id.exists' => 'Tipe item tidak valid.',
            'name.required' => 'Nama varian item wajib diisi.',
            'name.unique' => 'Nama varian item sudah digunakan pada tipe item ini.',
            'gold_weight.required' => 'Berat emas wajib diisi.',
            'gold_color.required' => 'Warna emas wajib dipilih.',
            'gold_color.in' => 'Warna emas tidak valid.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar harus jpg, jpeg, png, atau webp.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }
}
