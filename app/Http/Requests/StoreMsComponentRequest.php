<?php

namespace App\Http\Requests;

use App\Models\MsComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMsComponentRequest extends FormRequest
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

        foreach (['component_category', 'item', 'item_code'] as $field) {
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'component_category' => [
                'required',
                'string',
                'max:100',
                Rule::in([MsComponent::CATEGORY_KOMPONEN, MsComponent::CATEGORY_STOPPER]),
            ],
            'item' => ['required', 'string', 'max:255'],
            'item_code' => ['required', 'string', 'max:255'],
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
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $exists = MsComponent::query()
                    ->notDeleted()
                    ->whereIdentity(
                        (string) $this->input('component_category'),
                        (string) $this->input('item'),
                        (string) $this->input('item_code'),
                    )
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'item_code',
                        'Kombinasi kategori, item, dan kode sudah digunakan.',
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
            'component_category.required' => 'Kategori komponen wajib diisi.',
            'component_category.in' => 'Kategori komponen tidak valid.',
            'item.required' => 'Item wajib diisi.',
            'item.max' => 'Item maksimal 255 karakter.',
            'item_code.required' => 'Kode item wajib diisi.',
            'item_code.max' => 'Kode item maksimal 255 karakter.',
            'item_name.max' => 'Nama item maksimal 500 karakter.',
            'description.max' => 'Keterangan maksimal 5000 karakter.',
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPEG, PNG, atau WebP.',
            'photo.max' => 'Foto maksimal 5 MB.',
        ];
    }
}
