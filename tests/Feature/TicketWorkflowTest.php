<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\FormQuestion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Models\TicketStatus;
use App\Models\User;
use Livewire\Volt\Volt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('authorized user can view ticket details', function () {
    // Setup
    $role = Role::forceCreate([
        'ROLE_NAME' => 'legal',
        'ROLE_SLUG' => 'legal',
        'GUARD_NAME' => 'web',
        'IS_ACTIVE' => true,
    ]);

    $permission = Permission::factory()->create([
        'PERMISSION_NAME' => 'View Tickets',
        'PERMISSION_CODE' => 'tickets.view',
        'PERMISSION_GROUP' => 'tickets',
    ]);

    $role->permissions()->attach($permission->LGL_ROW_ID);

    $user = User::factory()->create([
        'USER_ROLE_ID' => $role->ROLE_ID,
    ]);

    $division = Division::factory()->create();
    $department = Department::factory()->create(['DIV_ID' => $division->LGL_ROW_ID]);
    $status = TicketStatus::firstOrCreate(
        ['LOV_VALUE' => 'open'],
        [
            'LOV_TYPE' => 'TICKET_STATUS',
            'LOV_DISPLAY_NAME' => 'Open',
            'IS_ACTIVE' => true,
        ]
    );

    // Seed form questions for dynamic answers
    $this->seed(\Database\Seeders\FormQuestionSeeder::class);

    $ticket = Ticket::create([
        'TCKT_NO' => 'TIC-TEST-001',
        'DIV_ID' => $division->LGL_ROW_ID,
        'DEPT_ID' => $department->LGL_ROW_ID,
        'TCKT_STS_ID' => $status->LGL_ID,
        'TCKT_CREATED_BY' => $user->LGL_ROW_ID,
        'TCKT_CREATED_DT' => now(),
    ]);

    // Save proposed doc title as dynamic answer
    $docTitleQ = FormQuestion::where('QUEST_CODE', 'proposed_document_title')->first();
    if ($docTitleQ) {
        TicketAnswer::create([
            'ANS_TICKET_ID' => $ticket->LGL_ROW_ID,
            'ANS_QUESTION_ID' => $docTitleQ->LGL_ROW_ID,
            'ANS_VALUE' => 'Test Document',
        ]);
    }

    $this->actingAs($user);

    Volt::test('contracts.show', ['contract' => $ticket->LGL_ROW_ID])
        ->assertSee('Test Document')
        ->assertSee('TIC-TEST-001')
        ->assertSee('Open');
});

test('legal user can move ticket to on process', function () {
    // Setup
    $role = Role::forceCreate([
        'ROLE_NAME' => 'legal',
        'ROLE_SLUG' => 'legal',
        'GUARD_NAME' => 'web',
        'IS_ACTIVE' => true,
    ]);

    $permission = Permission::factory()->create([
        'PERMISSION_NAME' => 'View Tickets',
        'PERMISSION_CODE' => 'tickets.view',
        'PERMISSION_GROUP' => 'tickets',
    ]);

    $role->permissions()->attach($permission->LGL_ROW_ID);

    $user = User::factory()->create([
        'USER_ROLE_ID' => $role->ROLE_ID,
    ]);

    $division = Division::factory()->create();
    $statusOpen = TicketStatus::firstOrCreate(['LOV_VALUE' => 'open'], ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_DISPLAY_NAME' => 'Open', 'IS_ACTIVE' => true]);
    $statusProcess = TicketStatus::firstOrCreate(['LOV_VALUE' => 'on_process'], ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_DISPLAY_NAME' => 'On Process', 'IS_ACTIVE' => true]);

    $ticket = Ticket::create([
        'TCKT_NO' => 'TIC-TEST-PROCESS',
        'DIV_ID' => $division->LGL_ROW_ID,
        'TCKT_STS_ID' => $statusOpen->LGL_ID,
        'TCKT_CREATED_BY' => $user->LGL_ROW_ID,
        'TCKT_CREATED_DT' => now(),
    ]);

    $this->actingAs($user);

    Volt::test('contracts.show', ['contract' => $ticket->LGL_ROW_ID])
        ->call('moveToOnProcess')
        ->assertDispatched('notify');

    $ticket->refresh();
    expect($ticket->TCKT_STS_ID)->toBe($statusProcess->LGL_ID);
    expect($ticket->TCKT_REVIEWED_BY)->toBe($user->LGL_ROW_ID);
});

test('legal user can move ticket to done and create contract', function () {
    // Setup
    $role = Role::forceCreate([
        'ROLE_NAME' => 'legal',
        'ROLE_SLUG' => 'legal',
        'GUARD_NAME' => 'web',
        'IS_ACTIVE' => true,
    ]);

    $permission = Permission::factory()->create([
        'PERMISSION_NAME' => 'View Tickets',
        'PERMISSION_CODE' => 'tickets.view',
        'PERMISSION_GROUP' => 'tickets',
    ]);

    $role->permissions()->attach($permission->LGL_ROW_ID);

    $user = User::factory()->create([
        'USER_ROLE_ID' => $role->ROLE_ID,
    ]);

    $division = Division::factory()->create(['REF_DIV_ID' => 'LEG', 'REF_DIV_NAME' => 'Legal']);
    $docType = DocumentType::firstOrCreate(['code' => 'perjanjian'], ['REF_DOC_TYPE_NAME' => 'Agreement']); // Contractable
    $statusProcess = TicketStatus::firstOrCreate(['LOV_VALUE' => 'on_process'], ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_DISPLAY_NAME' => 'On Process', 'IS_ACTIVE' => true]);
    $statusDone = TicketStatus::firstOrCreate(['LOV_VALUE' => 'done'], ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_DISPLAY_NAME' => 'Done', 'IS_ACTIVE' => true]);
    // Need ContractStatus 'active'
    ContractStatus::firstOrCreate(['LOV_VALUE' => 'active'], ['LOV_TYPE' => 'CONTRACT_STATUS', 'LOV_DISPLAY_NAME' => 'Active', 'IS_ACTIVE' => true]);

    // Seed finalization questions and basic/supporting questions
    $this->seed(\Database\Seeders\FormQuestionSeeder::class);

    $ticket = Ticket::create([
        'TCKT_NO' => 'TIC-TEST-DONE',
        'DIV_ID' => $division->LGL_ROW_ID,
        'TCKT_DOC_TYPE_ID' => $docType->LGL_ROW_ID,
        'TCKT_STS_ID' => $statusProcess->LGL_ID,
        'TCKT_CREATED_BY' => $user->LGL_ROW_ID,
        'TCKT_CREATED_DT' => now(),
        'TCKT_AGING_START_DT' => now()->subHours(1),
    ]);

    // Save dynamic answers needed for contract creation
    $docTitleQ = FormQuestion::where('QUEST_CODE', 'proposed_document_title')->first();
    $hasFinImpactQ = FormQuestion::where('QUEST_CODE', 'has_financial_impact')->first();
    $startDateQ = FormQuestion::where('QUEST_CODE', 'agreement_start_date')->first();
    $endDateQ = FormQuestion::where('QUEST_CODE', 'agreement_end_date')->first();

    if ($docTitleQ) {
        TicketAnswer::create(['ANS_TICKET_ID' => $ticket->LGL_ROW_ID, 'ANS_QUESTION_ID' => $docTitleQ->LGL_ROW_ID, 'ANS_VALUE' => 'Test Done']);
    }
    if ($hasFinImpactQ) {
        TicketAnswer::create(['ANS_TICKET_ID' => $ticket->LGL_ROW_ID, 'ANS_QUESTION_ID' => $hasFinImpactQ->LGL_ROW_ID, 'ANS_VALUE' => '0']);
    }
    if ($startDateQ) {
        TicketAnswer::create(['ANS_TICKET_ID' => $ticket->LGL_ROW_ID, 'ANS_QUESTION_ID' => $startDateQ->LGL_ROW_ID, 'ANS_VALUE' => now()->format('Y-m-d')]);
    }
    if ($endDateQ) {
        TicketAnswer::create(['ANS_TICKET_ID' => $ticket->LGL_ROW_ID, 'ANS_QUESTION_ID' => $endDateQ->LGL_ROW_ID, 'ANS_VALUE' => now()->addYear()->format('Y-m-d')]);
    }

    $this->actingAs($user);

    // Perjanjian requires finalization answers
    Volt::test('contracts.show', ['contract' => $ticket->LGL_ROW_ID])
        ->set('finalizationAnswers.signed_by_both_parties', '1')
        ->set('finalizationAnswers.saved_in_sharing_folder', '1')
        ->set('finalizationAnswers.mandatory_attachments_complete', '1')
        ->set('finalizationAnswers.finalization_remarks', 'All good')
        ->call('moveToDone')
        ->assertDispatched('notify');

    $ticket->refresh();
    expect($ticket->TCKT_STS_ID)->toBe($statusDone->LGL_ID);
    expect($ticket->contract)->not->toBeNull();
    expect($ticket->contract->CONTR_AGREE_NAME)->toBe('Test Done');
});

test('legal user can reject ticket', function () {
    $user = User::factory()->create();
    $legalUser = User::factory()->create();

    // Create Legal Role
    $legalRole = \App\Models\Role::firstOrCreate(
        ['ROLE_SLUG' => 'legal'],
        ['ROLE_NAME' => 'Legal', 'GUARD_NAME' => 'web', 'IS_ACTIVE' => true]
    );
    $legalUser->update(['USER_ROLE_ID' => $legalRole->ROLE_ID]);

    $division = Division::factory()->create();

    // Create 'rejected' status
    $statusRejected = TicketStatus::firstOrCreate(
        ['LOV_VALUE' => 'rejected'],
        ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_DISPLAY_NAME' => 'Rejected', 'IS_ACTIVE' => true]
    );

    // Create 'on_process' status
    $statusProcess = TicketStatus::firstOrCreate(
        ['LOV_VALUE' => 'on_process'],
        ['LOV_TYPE' => 'TICKET_STATUS', 'LOV_DISPLAY_NAME' => 'On Process', 'IS_ACTIVE' => true]
    );

    $docType = DocumentType::firstOrCreate(
        ['code' => 'perjanjian'],
        ['REF_DOC_TYPE_NAME' => 'Agreement', 'requires_contract' => true, 'REF_DOC_TYPE_IS_ACTIVE' => true]
    );

    $ticket = Ticket::create([
        'TCKT_NO' => 'TIC-TEST-REJECT',
        'DIV_ID' => $division->LGL_ROW_ID,
        'TCKT_DOC_TYPE_ID' => $docType->LGL_ROW_ID,
        'TCKT_STS_ID' => $statusProcess->LGL_ID,
        'TCKT_CREATED_BY' => $user->LGL_ROW_ID,
        'TCKT_CREATED_DT' => now(),
        'TCKT_AGING_START_DT' => now(),
    ]);

    $legalUser->refresh(); // Ensure role is loaded

    Livewire::actingAs($legalUser)
        ->test('contracts.show', ['contract' => $ticket->LGL_ROW_ID])
        ->set('rejectionReason', 'Invalid document')
        ->call('rejectTicket')
        ->assertDispatched('notify', function ($event, $data) {
            return $data['type'] === 'success';
        });

    $ticket->refresh();
    expect($ticket->TCKT_STS_ID)->toBe($statusRejected->LGL_ID);
    expect($ticket->TCKT_REJECT_REASON)->toBe('Invalid document');
});
