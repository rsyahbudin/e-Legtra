<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->division = Division::factory()->create();
    $this->department = Department::factory()->create(['DIV_ID' => $this->division->LGL_ROW_ID]);

    // Create role with reports.export permission
    $this->role = Role::factory()->create(['ROLE_SLUG' => 'legal']);
    $permission = Permission::firstOrCreate(
        ['PERMISSION_CODE' => 'reports.export'],
        [
            'PERMISSION_NAME' => 'Export Laporan',
            'PERMISSION_GROUP' => 'reports',
            'PERMISSION_DESC' => 'Export laporan ke Excel/CSV',
        ]
    );
    $this->role->permissions()->sync([$permission->LGL_ROW_ID]);

    $this->user = User::factory()->create([
        'USER_ROLE_ID' => $this->role->ROLE_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);
});

test('authenticated user with permission can export contracts to excel', function () {
    // Create a document type
    $docType = DocumentType::firstOrCreate(
        ['code' => 'perjanjian'],
        ['name' => 'Perjanjian', 'is_active' => true]
    );

    // Create contract status
    $status = ContractStatus::firstOrCreate(
        ['code' => 'active'],
        ['name' => 'Active', 'color' => 'green', 'is_active' => true, 'LOV_SEQ_NO' => 1]
    );

    // Create a ticket first
    $ticket = Ticket::create([
        'TCKT_NO' => 'TIC-TST-26010001',
        'TCKT_STS_ID' => $status->LGL_ROW_ID,
        'TCKT_DOC_TYPE_ID' => $docType->LGL_ROW_ID,
        'TCKT_CREATED_BY' => $this->user->LGL_ROW_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);

    // Create a contract
    Contract::create([
        'TCKT_ID' => $ticket->LGL_ROW_ID,
        'CONTR_NO' => 'CTR-TST-26010001',
        'CONTR_AGREE_NAME' => 'Test Agreement',
        'CONTR_DOC_TYPE_ID' => $docType->LGL_ROW_ID,
        'CONTR_DIV_ID' => $this->division->LGL_ROW_ID,
        'CONTR_DEPT_ID' => $this->department->LGL_ROW_ID,
        'CONTR_PIC_ID' => $this->user->LGL_ROW_ID,
        'CONTR_START_DT' => now(),
        'CONTR_END_DT' => now()->addYear(),
        'CONTR_STS_ID' => $status->LGL_ROW_ID,
        'CONTR_CREATED_BY' => $this->user->LGL_ROW_ID,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('contracts.export'));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition');
});

test('unauthenticated user cannot export contracts', function () {
    $response = $this->get(route('contracts.export'));

    $response->assertRedirect(route('login'));
});

test('user without permission cannot export contracts', function () {
    $noPermRole = Role::factory()->create(['ROLE_SLUG' => 'no-perm']);
    $user = User::factory()->create([
        'USER_ROLE_ID' => $noPermRole->ROLE_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);

    $response = $this->actingAs($user)
        ->get(route('contracts.export'));

    $response->assertForbidden();
});

test('contract export respects filters', function () {
    $docType = DocumentType::firstOrCreate(
        ['code' => 'perjanjian'],
        ['name' => 'Perjanjian', 'is_active' => true]
    );

    $status = ContractStatus::firstOrCreate(
        ['code' => 'active'],
        ['name' => 'Active', 'color' => 'green', 'is_active' => true, 'LOV_SEQ_NO' => 1]
    );

    $ticket = Ticket::create([
        'TCKT_NO' => 'TIC-TST-26010002',
        'TCKT_STS_ID' => $status->LGL_ROW_ID,
        'TCKT_DOC_TYPE_ID' => $docType->LGL_ROW_ID,
        'TCKT_CREATED_BY' => $this->user->LGL_ROW_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);

    Contract::create([
        'TCKT_ID' => $ticket->LGL_ROW_ID,
        'CONTR_NO' => 'CTR-TST-26010002',
        'CONTR_AGREE_NAME' => 'Filtered Agreement',
        'CONTR_DOC_TYPE_ID' => $docType->LGL_ROW_ID,
        'CONTR_DIV_ID' => $this->division->LGL_ROW_ID,
        'CONTR_DEPT_ID' => $this->department->LGL_ROW_ID,
        'CONTR_PIC_ID' => $this->user->LGL_ROW_ID,
        'CONTR_START_DT' => now(),
        'CONTR_END_DT' => now()->addYear(),
        'CONTR_STS_ID' => $status->LGL_ROW_ID,
        'CONTR_CREATED_BY' => $this->user->LGL_ROW_ID,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('contracts.export', [
            'type' => 'perjanjian',
            'division' => $this->division->LGL_ROW_ID,
        ]));

    $response->assertSuccessful();
});
