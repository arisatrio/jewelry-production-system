<?php

namespace App\Http\Requests;

use App\Models\MsChain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMsChainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['chain_category', 'item', 'item_code'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $merge[$field] = trim($value);
            }
        }

        foreach (['item_name', 'description'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed = trim($value);
                $merge[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chain_category' => [
                'required',
                'string',
                'max:100',
                Rule::in([MsChain::CATEGORY_BRACELET, MsChain::CATEGORY_PENDANT]),
            ],
            'item' => ['required', 'string', 'max:255'],
            'item_code' => ['required', 'string', 'max:255'],
            'length' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999999.99'],
            'item_name' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! is_numeric($this->input('length'))) {
                    return;
                }

                $exists = MsChain::query()
                    ->notDeleted()
                    ->whereIdentity(
                        (string) $this->input('chain_category'),
                        (string) $this->input('item'),
                        (string) $this->input('item_code'),
                        number_format((float) $this->input('length'), 2, '.', ''),
                    )
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'item_code',
                        'Kombinasi kategori, item, kode, dan panjang sudah digunakan.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chain_category.required' => 'Kategori chain wajib diisi.',
            'chain_category.in' => 'Kategori chain tidak valid.',
            'item.required' => 'Item wajib diisi.',
            'item.max' => 'Item maksimal 255 karakter.',
            'item_code.required' => 'Kode item wajib diisi.',
            'item_code.max' => 'Kode item maksimal 255 karakter.',
            'length.required' => 'Panjang wajib diisi.',
            'length.numeric' => 'Panjang harus berupa angka.',
            'length.gt' => 'Panjang harus lebih dari 0.',
            'length.decimal' => 'Panjang maksimal 2 angka di belakang koma.',
            'item_name.max' => 'Nama item maksimal 500 karakter.',
            'description.max' => 'Keterangan maksimal 5000 karakter.',
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPEG, PNG, atau WebP.',
            'photo.max' => 'Foto maksimal 5 MB.',
        ];
    }
}
