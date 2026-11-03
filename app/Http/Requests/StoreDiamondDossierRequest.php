<?php

namespace App\Http\Requests;

use App\Support\DiamondDossierInventory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiamondDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $crt = $this->input('crt');

        if (! is_string($crt) && ! is_numeric($crt)) {
            return;
        }

        $normalized = str_replace(',', '.', trim((string) $crt));
        $normalized = preg_replace('/\.(?=.*\.)/', '', $normalized) ?? $normalized;

        $this->merge([
            'crt' => $normalized,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function fieldRules(?int $ignoreDiamondId = null): array
    {
        return [
            'doc_no' => ['nullable', 'string', 'max:50'],
            'diamond_type' => [
                'required',
                'string',
                Rule::in(DiamondDossierInventory::diamondTypes()),
            ],
            'shape_id' => ['required', 'integer', 'min:1'],
            'color' => ['required', 'string', 'max:20'],
            'crt' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,3})?$/'],
            'certificate' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:100'],
            'entry_date' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateShapeId($validator);

            if (! $this->isMethod('POST')) {
                return;
            }

            $this->validateDocNo($validator, null);
            $this->validateCertificateUnique($validator, null);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::validationMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'doc_no.string' => 'Kode batu harus berupa teks.',
            'doc_no.max' => 'Kode batu maksimal 50 karakter.',
            'diamond_type.required' => 'Tipe batu wajib dipilih.',
            'diamond_type.string' => 'Tipe batu harus berupa teks.',
            'diamond_type.in' => 'Tipe batu tidak valid.',
            'shape_id.required' => 'Bentuk wajib dipilih.',
            'shape_id.integer' => 'Bentuk tidak valid.',
            'shape_id.min' => 'Bentuk wajib dipilih.',
            'color.required' => 'Warna wajib diisi.',
            'color.string' => 'Warna harus berupa teks.',
            'color.max' => 'Warna maksimal 20 karakter.',
            'crt.required' => 'CRT wajib diisi.',
            'crt.numeric' => 'CRT harus berupa angka.',
            'crt.gt' => 'CRT harus lebih dari 0.',
            'crt.regex' => 'CRT maksimal 3 angka di belakang koma.',
            'certificate.string' => 'Nomor sertifikat harus berupa teks.',
            'certificate.max' => 'Nomor sertifikat maksimal 100 karakter.',
            'supplier.string' => 'Supplier harus berupa teks.',
            'supplier.max' => 'Supplier maksimal 100 karakter.',
            'entry_date.required' => 'Tanggal masuk wajib diisi.',
            'entry_date.date' => 'Tanggal masuk tidak valid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'doc_no' => 'kode batu',
            'diamond_type' => 'tipe batu',
            'shape_id' => 'bentuk',
            'color' => 'warna',
            'crt' => 'CRT',
            'certificate' => 'nomor sertifikat',
            'supplier' => 'supplier',
            'entry_date' => 'tanggal masuk',
        ];
    }

    protected function validateShapeId(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $shapeId = (int) $this->input('shape_id');

        if ($shapeId <= 0 || ! Schema::connection('third')->hasTable('msshape')) {
            $validator->errors()->add('shape_id', 'Bentuk tidak valid.');

            return;
        }

        $exists = DB::connection('third')
            ->table('msshape')
            ->where('row_id', $shapeId)
            ->when(
                Schema::connection('third')->hasColumn('msshape', 'is_deleted'),
                fn ($query) => $query->where('is_deleted', 0),
            )
            ->exists();

        if (! $exists) {
            $validator->errors()->add('shape_id', 'Bentuk tidak valid.');
        }
    }

    protected function validateDocNo(Validator $validator, ?int $ignoreDiamondId): void
    {
        $docNo = trim((string) $this->input('doc_no', ''));

        if ($docNo === '' || ! Schema::connection('third')->hasTable('trdiamond')) {
            return;
        }

        $query = DB::connection('third')
            ->table('trdiamond')
            ->where('doc_no', $docNo)
            ->where('is_deleted', 0);

        if ($ignoreDiamondId !== null) {
            $query->where('row_id', '!=', $ignoreDiamondId);
        }

        if ($query->exists()) {
            $validator->errors()->add('doc_no', 'Kode batu sudah digunakan.');
        }
    }

    protected function validateCertificateUnique(Validator $validator, ?int $ignoreDiamondId): void
    {
        self::assertCertificateUniqueForDossier(
            $validator,
            (string) $this->input('diamond_type'),
            $this->input('certificate'),
            $ignoreDiamondId,
        );
    }

    public static function assertCertificateUniqueForDossier(
        Validator $validator,
        string $diamondType,
        mixed $certificate,
        ?int $ignoreDiamondId = null,
    ): void {
        if ($diamondType !== 'Dossier') {
            return;
        }

        $certificate = trim((string) ($certificate ?? ''));

        if ($certificate === '' || ! Schema::connection('third')->hasTable('trdiamond')) {
            return;
        }

        $query = DB::connection('third')
            ->table('trdiamond')
            ->where('is_deleted', 0)
            ->where('diamond_type', 'Dossier')
            ->where('certificate', $certificate);

        if ($ignoreDiamondId !== null) {
            $query->where('row_id', '!=', $ignoreDiamondId);
        }

        if ($query->exists()) {
            $validator->errors()->add(
                'certificate',
                'Nomor sertifikat sudah digunakan untuk batu tipe Dossier.',
            );
        }
    }

    protected function passedValidation(): void
    {
        $docNo = trim((string) $this->input('doc_no', ''));

        if ($docNo === '') {
            return;
        }

        $this->merge([
            'doc_no' => $docNo,
        ]);
    }
}
