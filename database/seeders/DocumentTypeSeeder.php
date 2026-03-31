<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'perjanjian',
                'REF_DOC_TYPE_NAME' => 'Perjanjian',
                'description' => 'Dokumen Perjanjian Kerjasama',
                'requires_contract' => true,
                'REF_DOC_TYPE_IS_ACTIVE' => true,
                'DOC_TYPE_SORT_ORDER' => 1,
            ],
            [
                'code' => 'nda',
                'REF_DOC_TYPE_NAME' => 'NDA',
                'description' => 'Non-Disclosure Agreement',
                'requires_contract' => true,
                'REF_DOC_TYPE_IS_ACTIVE' => true,
                'DOC_TYPE_SORT_ORDER' => 2,
            ],
            [
                'code' => 'surat_kuasa',
                'REF_DOC_TYPE_NAME' => 'Surat Kuasa',
                'description' => 'Dokumen Surat Kuasa',
                'requires_contract' => false,
                'REF_DOC_TYPE_IS_ACTIVE' => true,
                'DOC_TYPE_SORT_ORDER' => 3,
            ],
            [
                'code' => 'pendapat_hukum',
                'REF_DOC_TYPE_NAME' => 'Pendapat Hukum',
                'description' => 'Dokumen Pendapat Hukum / Legal Opinion',
                'requires_contract' => false,
                'REF_DOC_TYPE_IS_ACTIVE' => true,
                'DOC_TYPE_SORT_ORDER' => 4,
            ],
            [
                'code' => 'surat_pernyataan',
                'REF_DOC_TYPE_NAME' => 'Surat Pernyataan',
                'description' => 'Dokumen Surat Pernyataan',
                'requires_contract' => false,
                'REF_DOC_TYPE_IS_ACTIVE' => true,
                'DOC_TYPE_SORT_ORDER' => 5,
            ],
            [
                'code' => 'surat_lainnya',
                'REF_DOC_TYPE_NAME' => 'Surat Lainnya',
                'description' => 'Dokumen Surat-surat Lainnya',
                'requires_contract' => false,
                'REF_DOC_TYPE_IS_ACTIVE' => true,
                'DOC_TYPE_SORT_ORDER' => 6,
            ],
        ];

        foreach ($types as $type) {
            \App\Models\DocumentType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
