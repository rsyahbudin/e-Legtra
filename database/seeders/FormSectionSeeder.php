<?php

namespace Database\Seeders;

use App\Models\FormSection;
use Illuminate\Database\Seeder;

class FormSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'code' => 'basic',
                'label' => 'Basic Information',
                'description' => null,
                'sort' => 1,
                'show_create' => true,
                'show_detail' => true,
            ],
            [
                'code' => 'form',
                'label' => 'Document Details',
                'description' => null,
                'sort' => 2,
                'show_create' => true,
                'show_detail' => true,
            ],
            [
                'code' => 'supporting',
                'label' => 'Supporting Documents',
                'description' => null,
                'sort' => 3,
                'show_create' => true,
                'show_detail' => true,
            ],
            [
                'code' => 'finalization',
                'label' => 'Finalization Checklist',
                'description' => 'Please answer all questions before completing the ticket',
                'sort' => 4,
                'show_create' => false,
                'show_detail' => true,
            ],
        ];

        foreach ($sections as $s) {
            FormSection::updateOrCreate(
                ['SECT_CODE' => $s['code']],
                [
                    'SECT_LABEL' => $s['label'],
                    'SECT_DESCRIPTION' => $s['description'],
                    'SECT_SORT_ORDER' => $s['sort'],
                    'SECT_IS_ACTIVE' => true,
                    'SECT_SHOW_ON_CREATE' => $s['show_create'],
                    'SECT_SHOW_ON_DETAIL' => $s['show_detail'],
                ]
            );
        }
    }
}
