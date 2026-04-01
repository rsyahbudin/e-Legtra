<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\FormQuestion;
use Illuminate\Database\Seeder;

class FormQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $perjanjianId = DocumentType::where('code', 'perjanjian')->value('LGL_ROW_ID');
        $ndaId = DocumentType::where('code', 'nda')->value('LGL_ROW_ID');
        $suratKuasaId = DocumentType::where('code', 'surat_kuasa')->value('LGL_ROW_ID');

        $questions = [
            // ─── Basic section (applies to ALL doc types, doc_type = null) ───
            ['doc_type' => null, 'section' => 'basic', 'code' => 'has_financial_impact', 'label' => 'Financial Impact (Income/Expenditure)', 'type' => 'boolean', 'width' => 'full', 'required' => true, 'sort' => 1],
            ['doc_type' => null, 'section' => 'basic', 'code' => 'payment_type', 'label' => 'Payment Type', 'type' => 'select', 'width' => 'full', 'required' => true, 'sort' => 2, 'depends_on' => 'has_financial_impact', 'depends_value' => '1', 'options' => [['value' => 'pay', 'label' => 'Pay'], ['value' => 'receive_payment', 'label' => 'Receive Payment']]],
            ['doc_type' => null, 'section' => 'basic', 'code' => 'recurring_desc', 'label' => 'Recurring Description (Optional)', 'type' => 'text', 'width' => 'full', 'required' => false, 'sort' => 3, 'depends_on' => 'payment_type', 'depends_value' => 'pay', 'placeholder' => 'Example: Monthly, Every 3 months, etc'],
            ['doc_type' => null, 'section' => 'basic', 'code' => 'proposed_document_title', 'label' => 'Proposed Document Title', 'type' => 'text', 'width' => 'full', 'required' => true, 'sort' => 4, 'placeholder' => 'Enter proposed document title'],
            ['doc_type' => null, 'section' => 'basic', 'code' => 'draft_document', 'label' => 'Draft Document (Optional)', 'type' => 'file', 'width' => 'full', 'required' => false, 'sort' => 5, 'description' => 'PDF or Word, max 10MB', 'accept' => '.pdf,.doc,.docx', 'max_size_kb' => 10240],

            // ─── Supporting section (applies to ALL doc types, doc_type = null) ───
            ['doc_type' => null, 'section' => 'supporting', 'code' => 'tat_legal_compliance', 'label' => 'Legal Turn-Around-Time Compliance', 'type' => 'boolean', 'width' => 'full', 'required' => true, 'sort' => 1],
            ['doc_type' => null, 'section' => 'supporting', 'code' => 'mandatory_documents', 'label' => 'Mandatory Documents', 'type' => 'file', 'width' => 'full', 'required' => true, 'sort' => 2, 'description' => 'Deed of Incorporation, Board of Directors Composition, Last Amendment, ID Card, etc. (Max 10MB per file, multiple uploads allowed)', 'max_size_kb' => 10240, 'is_multiple' => true],
            ['doc_type' => null, 'section' => 'supporting', 'code' => 'approval_document', 'label' => 'Legal Request Permit/Approval from related Head or Leader', 'type' => 'file', 'width' => 'full', 'required' => true, 'sort' => 3, 'description' => 'Screenshot of email/correspondence approval (PDF or image, max 5MB)', 'accept' => '.pdf,.jpg,.jpeg,.png', 'max_size_kb' => 5120],

            // ─── Perjanjian form fields ───
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'counterpart_name', 'label' => 'Counterpart / Other Party Name', 'type' => 'text', 'width' => 'full', 'required' => true, 'sort' => 1, 'placeholder' => 'Name of other party in the agreement'],
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'agreement_start_date', 'label' => 'Estimated Start Date of Agreement', 'type' => 'date', 'width' => 'half', 'required' => true, 'sort' => 2],
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'agreement_duration', 'label' => 'Duration of Agreement', 'type' => 'text', 'width' => 'half', 'required' => true, 'sort' => 3, 'placeholder' => 'Example: 2 years, 12 months'],
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'is_auto_renewal', 'label' => 'Auto Renewal', 'type' => 'boolean', 'width' => 'full', 'required' => true, 'sort' => 4],
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'renewal_period', 'label' => 'Auto Renewal Period', 'type' => 'text', 'width' => 'half', 'required' => true, 'sort' => 5, 'depends_on' => 'is_auto_renewal', 'depends_value' => '1', 'placeholder' => 'Example: 1 year, 6 months, 90 days'],
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'renewal_notification_days', 'label' => 'Notification Period Before Renewal (Days)', 'type' => 'number', 'width' => 'half', 'required' => true, 'sort' => 6, 'depends_on' => 'is_auto_renewal', 'depends_value' => '1', 'placeholder' => 'Example: 30', 'description' => 'System will send notification before renewal date'],
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'agreement_end_date', 'label' => 'End Date of Agreement', 'type' => 'date', 'width' => 'half', 'required' => true, 'sort' => 7, 'depends_on' => 'is_auto_renewal', 'depends_value' => '0'],
            ['doc_type' => $perjanjianId, 'section' => 'form', 'code' => 'termination_notification_days', 'label' => 'Notification Period Before Termination (Days)', 'type' => 'number', 'width' => 'half', 'required' => false, 'sort' => 8, 'depends_on' => 'is_auto_renewal', 'depends_value' => '0', 'placeholder' => 'Example: 60'],

            // ─── NDA form fields (same structure as perjanjian) ───
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_counterpart_name', 'label' => 'Counterpart / Other Party Name', 'type' => 'text', 'width' => 'full', 'required' => true, 'sort' => 1, 'placeholder' => 'Name of other party'],
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_agreement_start_date', 'label' => 'Estimated Start Date of NDA', 'type' => 'date', 'width' => 'half', 'required' => true, 'sort' => 2],
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_agreement_duration', 'label' => 'Duration of NDA', 'type' => 'text', 'width' => 'half', 'required' => true, 'sort' => 3, 'placeholder' => 'Example: 2 years, 12 months'],
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_is_auto_renewal', 'label' => 'Auto Renewal', 'type' => 'boolean', 'width' => 'full', 'required' => true, 'sort' => 4],
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_renewal_period', 'label' => 'Auto Renewal Period', 'type' => 'text', 'width' => 'half', 'required' => true, 'sort' => 5, 'depends_on' => 'nda_is_auto_renewal', 'depends_value' => '1', 'placeholder' => 'Example: 1 year, 6 months'],
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_renewal_notification_days', 'label' => 'Notification Period Before Renewal (Days)', 'type' => 'number', 'width' => 'half', 'required' => true, 'sort' => 6, 'depends_on' => 'nda_is_auto_renewal', 'depends_value' => '1', 'placeholder' => 'Example: 30'],
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_agreement_end_date', 'label' => 'End Date of NDA', 'type' => 'date', 'width' => 'half', 'required' => true, 'sort' => 7, 'depends_on' => 'nda_is_auto_renewal', 'depends_value' => '0'],
            ['doc_type' => $ndaId, 'section' => 'form', 'code' => 'nda_termination_notification_days', 'label' => 'Notification Period Before Termination (Days)', 'type' => 'number', 'width' => 'half', 'required' => false, 'sort' => 8, 'depends_on' => 'nda_is_auto_renewal', 'depends_value' => '0', 'placeholder' => 'Example: 60'],

            // ─── Surat Kuasa form fields ───
            ['doc_type' => $suratKuasaId, 'section' => 'form', 'code' => 'kuasa_pemberi', 'label' => 'Grantor (Pemberi Kuasa)', 'type' => 'text', 'width' => 'half', 'required' => true, 'sort' => 1, 'placeholder' => 'Name of grantor'],
            ['doc_type' => $suratKuasaId, 'section' => 'form', 'code' => 'kuasa_penerima', 'label' => 'Grantee (Penerima Kuasa)', 'type' => 'text', 'width' => 'half', 'required' => true, 'sort' => 2, 'placeholder' => 'Name of grantee'],
            ['doc_type' => $suratKuasaId, 'section' => 'form', 'code' => 'kuasa_start_date', 'label' => 'Estimated Start Date of Power of Attorney', 'type' => 'date', 'width' => 'half', 'required' => true, 'sort' => 3],
            ['doc_type' => $suratKuasaId, 'section' => 'form', 'code' => 'kuasa_end_date', 'label' => 'Power of Attorney End Date', 'type' => 'date', 'width' => 'half', 'required' => true, 'sort' => 4],

            // ─── Finalization checklist (Global for all documents that reach Done phase) ───
            ['doc_type' => null, 'section' => 'finalization', 'code' => 'signed_by_both_parties', 'label' => 'Has the document been signed by both parties?', 'type' => 'boolean', 'width' => 'full', 'required' => true, 'sort' => 1],
            ['doc_type' => null, 'section' => 'finalization', 'code' => 'saved_in_sharing_folder', 'label' => 'Has the final document been saved in the internal sharing folder?', 'type' => 'boolean', 'width' => 'full', 'required' => true, 'sort' => 2],
            ['doc_type' => null, 'section' => 'finalization', 'code' => 'mandatory_attachments_complete', 'label' => 'Are all mandatory attachments complete?', 'type' => 'boolean', 'width' => 'full', 'required' => true, 'sort' => 3],
            ['doc_type' => null, 'section' => 'finalization', 'code' => 'final_contract_file', 'label' => 'Upload Final Document', 'type' => 'file', 'width' => 'full', 'required' => false, 'sort' => 4, 'description' => 'PDF or Word, max 10MB', 'accept' => '.pdf,.doc,.docx', 'max_size_kb' => 10240],
            ['doc_type' => null, 'section' => 'finalization', 'code' => 'finalization_remarks', 'label' => 'Remarks (Optional)', 'type' => 'text', 'width' => 'full', 'required' => false, 'sort' => 5, 'placeholder' => 'Additional notes or remarks (max 1000 characters)', 'description' => 'Maximum 1000 characters'],
        ];

        foreach ($questions as $q) {
            FormQuestion::updateOrCreate(
                ['QUEST_CODE' => $q['code']],
                [
                    'QUEST_DOC_TYPE_ID' => $q['doc_type'],
                    'QUEST_SECTION' => $q['section'],
                    'QUEST_LABEL' => $q['label'],
                    'QUEST_TYPE' => $q['type'],
                    'QUEST_WIDTH' => $q['width'] ?? 'full',
                    'QUEST_IS_REQUIRED' => $q['required'],
                    'QUEST_SORT_ORDER' => $q['sort'],
                    'QUEST_IS_ACTIVE' => true,
                    'QUEST_DEPENDS_ON' => $q['depends_on'] ?? null,
                    'QUEST_DEPENDS_VALUE' => $q['depends_value'] ?? null,
                    'QUEST_PLACEHOLDER' => $q['placeholder'] ?? null,
                    'QUEST_DESCRIPTION' => $q['description'] ?? null,
                    'QUEST_OPTIONS' => $q['options'] ?? null,
                    'QUEST_MAX_SIZE_KB' => $q['max_size_kb'] ?? null,
                    'QUEST_ACCEPT' => $q['accept'] ?? null,
                    'QUEST_IS_MULTIPLE' => $q['is_multiple'] ?? false,
                ]
            );
        }
    }
}
