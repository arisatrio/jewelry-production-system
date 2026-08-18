<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class QuickLoginRequest extends FormRequest
{
    private ?User $resolvedUser = null;

    private bool $userResolved = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->loginUser() === null) {
                    $validator->errors()->add('user_id', __('auth.failed'));
                }
            },
        ];
    }

    public function loginUser(): ?User
    {
        if (! $this->userResolved) {
            $this->resolvedUser = User::query()
                ->where('user_id', $this->string('user_id')->toString())
                ->first();
            $this->userResolved = true;
        }

        return $this->resolvedUser;
    }
}
