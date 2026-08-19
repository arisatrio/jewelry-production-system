<?php

namespace App\Support;

class SpkPermissions
{
    public const VIEW = 'spk.view';

    public const CREATE = 'spk.create';

    public const EDIT_DRAFT = 'spk.edit_draft';

    public const SUBMIT = 'spk.submit';

    public const APPROVE = 'spk.approve';

    public const REJECT = 'spk.reject';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT_DRAFT,
            self::SUBMIT,
            self::APPROVE,
            self::REJECT,
        ];
    }

    /**
     * @return array<string, array{display_name: string, description: string, category: string}>
     */
    public static function definitions(): array
    {
        return [
            self::VIEW => [
                'display_name' => 'View SPK',
                'description' => 'Can view SPK list and detail',
                'category' => 'read',
            ],
            self::CREATE => [
                'display_name' => 'Create SPK',
                'description' => 'Can create new SPK draft',
                'category' => 'write',
            ],
            self::EDIT_DRAFT => [
                'display_name' => 'Edit SPK Draft',
                'description' => 'Can edit SPK while status is Draft',
                'category' => 'write',
            ],
            self::SUBMIT => [
                'display_name' => 'Submit SPK',
                'description' => 'Can send draft SPK to Manager (SPK010)',
                'category' => 'write',
            ],
            self::APPROVE => [
                'display_name' => 'Approve SPK',
                'description' => 'Legacy Manager permission. Approve SPK (Disetujui) is granted to all authenticated users.',
                'category' => 'approve',
            ],
            self::REJECT => [
                'display_name' => 'Reject SPK',
                'description' => 'Can reject pending SPK back to Draft',
                'category' => 'approve',
            ],
        ];
    }
}
