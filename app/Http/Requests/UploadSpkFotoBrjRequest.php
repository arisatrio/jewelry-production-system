<?php

namespace App\Http\Requests;

use App\Models\Production;
use Illuminate\Foundation\Http\FormRequest;

class UploadSpkFotoBrjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('last_weight')) {
            $raw = $this->input('last_weight');

            $this->merge([
                'last_weight' => filled($raw)
                    ? str_replace(',', '.', trim((string) $raw))
                    : $raw,
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Production|null $production */
        $production = $this->route('production');
        $needsFoto = $production === null || blank($production->foto_brj);

        $fotoRules = ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif'];

        if ($needsFoto && ! $this->hasFile('foto')) {
            array_unshift($fotoRules, 'required');
        }

        $lastWeightRules = $this->boolean('with_last_weight')
            ? ['required', 'numeric', 'min:0', 'decimal:0,2']
            : ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,2'];

        return [
            'foto' => $fotoRules,
            'last_weight' => $lastWeightRules,
            'with_last_weight' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Pilih file gambar foto barang jadi.',
            'foto.mimes' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.',
            'foto.max' => 'Ukuran gambar maksimal 10 MB.',
            'last_weight.required' => 'Berat akhir wajib diisi.',
            'last_weight.numeric' => 'Berat akhir harus berupa angka.',
            'last_weight.decimal' => 'Berat akhir maksimal 2 angka di belakang koma.',
        ];
    }
}
