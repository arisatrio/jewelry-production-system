<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

class UpdateDiamondDossierRequest extends StoreDiamondDossierRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateShapeId($validator);
            $this->validateCertificateUnique(
                $validator,
                (int) $this->route('diamond'),
            );
        });
    }
}
