<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'CODE' => 'perjanjian',
                'REF_DOC_TYPE_NAME' => 'Perjanjian',
                'DESCRIPTION' => 'Dokumen Perjanjian Kerjasama',
                'REQUIRES_CONTRACT' => 1,
                'REF_DOC_TYPE_IS_ACTIVE' => 1,
                'DOC_TYPE_SORT_ORDER' => 1,
            ],
            [
                'CODE' => 'nda',
                'REF_DOC_TYPE_NAME' => 'NDA',
                'DESCRIPTION' => 'Non-Disclosure Agreement',
                'REQUIRES_CONTRACT' => 1,
                'REF_DOC_TYPE_IS_ACTIVE' => 1,
                'DOC_TYPE_SORT_ORDER' => 2,
            ],
            [
                'CODE' => 'surat_kuasa',
                'REF_DOC_TYPE_NAME' => 'Surat Kuasa',
                'DESCRIPTION' => 'Dokumen Surat Kuasa',
                'REQUIRES_CONTRACT' => 0,
                'REF_DOC_TYPE_IS_ACTIVE' => 1,
                'DOC_TYPE_SORT_ORDER' => 3,
            ],
            [
                'CODE' => 'pendapat_hukum',
                'REF_DOC_TYPE_NAME' => 'Pendapat Hukum',
                'DESCRIPTION' => 'Dokumen Pendapat Hukum / Legal Opinion',
                'REQUIRES_CONTRACT' => 0,
                'REF_DOC_TYPE_IS_ACTIVE' => 1,
                'DOC_TYPE_SORT_ORDER' => 4,
            ],
            [
                'CODE' => 'surat_pernyataan',
                'REF_DOC_TYPE_NAME' => 'Surat Pernyataan',
                'DESCRIPTION' => 'Dokumen Surat Pernyataan',
                'REQUIRES_CONTRACT' => 0,
                'REF_DOC_TYPE_IS_ACTIVE' => 1,
                'DOC_TYPE_SORT_ORDER' => 5,
            ],
            [
                'CODE' => 'surat_lainnya',
                'REF_DOC_TYPE_NAME' => 'Surat Lainnya',
                'DESCRIPTION' => 'Dokumen Surat-surat Lainnya',
                'REQUIRES_CONTRACT' => 0,
                'REF_DOC_TYPE_IS_ACTIVE' => 1,
                'DOC_TYPE_SORT_ORDER' => 6,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['CODE' => $type['CODE']],
                $type
            );
        }
    }
}
