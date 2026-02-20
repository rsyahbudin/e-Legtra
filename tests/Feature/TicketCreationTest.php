<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    // Create divisions and departments
    $this->division = Division::factory()->create();
    $this->department = Department::factory()->create(['DIV_ID' => $this->division->LGL_ROW_ID]);
});

use Livewire\Volt\Volt;

// ...

test('superadmin can create ticket', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->role->update(['ROLE_SLUG' => 'super-admin']);

    $this->actingAs($superAdmin);

    Volt::test('contracts.create')
        ->set('DIV_ID', $this->division->LGL_ROW_ID)
        ->set('DEPT_ID', $this->department->LGL_ROW_ID)
        ->set('has_financial_impact', true)
        ->set('TCKT_PAYMENT_TYPE', 'pay')
        ->set('TCKT_RECURRING_DESC', 'Monthly')
        ->set('proposed_document_title', 'Test Document')
        ->set('document_type', 'perjanjian')
        ->set('counterpart_name', 'Test Company')
        ->set('agreement_start_date', now()->addDays(10)->format('Y-m-d'))
        ->set('agreement_duration', '2 years')
        ->set('is_auto_renewal', false)
        ->set('agreement_end_date', now()->addYears(2)->format('Y-m-d'))
        ->set('tat_legal_compliance', true)
        ->set('mandatory_documents', [UploadedFile::fake()->create('doc.pdf')])
        ->set('approval_document', UploadedFile::fake()->image('approval.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::count())->toBe(1);
});

test('user with tickets.create permission can create ticket', function () {
    // Create user role with tickets.create permission
    $role = Role::factory()->create(['ROLE_SLUG' => 'user']);
    $permission = Permission::where('PERMISSION_CODE', 'tickets.create')->first();

    if (! $permission) {
        $permission = Permission::create([
            'PERMISSION_NAME' => 'Buat Ticket',
            'PERMISSION_CODE' => 'tickets.create',
            'PERMISSION_GROUP' => 'tickets',
            'PERMISSION_DESC' => 'Membuat ticket baru',
        ]);
    }

    $role->permissions()->sync([$permission->LGL_ROW_ID]);

    $user = User::factory()->create([
        'USER_ROLE_ID' => $role->ROLE_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);

    $this->actingAs($user);

    Volt::test('contracts.create')
        ->set('DIV_ID', $this->division->LGL_ROW_ID)
        ->set('DEPT_ID', $this->department->LGL_ROW_ID)
        ->set('has_financial_impact', false)
        ->set('proposed_document_title', 'User Created Document')
        ->set('document_type', 'surat_kuasa')
        ->set('kuasa_pemberi', 'John Doe')
        ->set('kuasa_penerima', 'Jane Smith')
        ->set('kuasa_start_date', now()->addDays(5)->format('Y-m-d'))
        ->set('kuasa_end_date', now()->addMonths(6)->format('Y-m-d'))
        ->set('tat_legal_compliance', true)
        ->set('mandatory_documents', [UploadedFile::fake()->create('doc.pdf')])
        ->set('approval_document', UploadedFile::fake()->image('approval.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::count())->toBe(1);
    expect(Ticket::first()->TCKT_CREATED_BY)->toBe($user->LGL_ROW_ID);
});

test('legal role user can create ticket', function () {
    // Create legal role with tickets.create permission
    $role = Role::where('ROLE_SLUG', 'legal')->first();
    if (! $role) {
        $role = Role::factory()->create(['ROLE_SLUG' => 'legal']);
    }

    $permission = Permission::where('PERMISSION_CODE', 'tickets.create')->first();
    if (! $permission) {
        $permission = Permission::create([
            'PERMISSION_NAME' => 'Buat Ticket',
            'PERMISSION_CODE' => 'tickets.create',
            'PERMISSION_GROUP' => 'tickets',
            'PERMISSION_DESC' => 'Membuat ticket baru',
        ]);
    }

    $role->permissions()->sync([$permission->LGL_ROW_ID]);

    $legal = User::factory()->create([
        'USER_ROLE_ID' => $role->ROLE_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);

    $this->actingAs($legal);

    Volt::test('contracts.create')
        ->set('DIV_ID', $this->division->LGL_ROW_ID)
        ->set('DEPT_ID', $this->department->LGL_ROW_ID)
        ->set('has_financial_impact', true)
        ->set('TCKT_PAYMENT_TYPE', 'pay')
        ->set('TCKT_RECURRING_DESC', 'Monthly')
        ->set('proposed_document_title', 'Legal Team Document')
        ->set('document_type', 'nda')
        ->set('counterpart_name', 'Partner Company')
        ->set('agreement_start_date', now()->format('Y-m-d'))
        ->set('agreement_duration', '1 year')
        ->set('is_auto_renewal', true)
        ->set('renewal_period', '1 year')
        ->set('renewal_notification_days', 30)
        ->set('tat_legal_compliance', false)
        ->set('mandatory_documents', [UploadedFile::fake()->create('doc.pdf')])
        ->set('approval_document', UploadedFile::fake()->image('approval.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    expect(Ticket::count())->toBe(1);
});

test('user without tickets.create permission cannot create ticket', function () {
    // Create user without ticket creation permission
    $role = Role::factory()->create(['ROLE_SLUG' => 'no-permission']);
    $user = User::factory()->create([
        'USER_ROLE_ID' => $role->ROLE_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);

    $this->actingAs($user);

    Volt::test('contracts.create')
        ->assertStatus(403);
});

test('permission check uses tickets.create not contracts.create', function () {
    // This test verifies that the code checks for tickets.create permission
    $role = Role::factory()->create(['ROLE_SLUG' => 'test-user']);

    // Give ONLY contracts.create (wrong permission)
    $wrongPermission = Permission::firstOrCreate(
        ['PERMISSION_CODE' => 'contracts.create'],
        [
            'PERMISSION_NAME' => 'Wrong Permission',
            'PERMISSION_GROUP' => 'contracts',
            'PERMISSION_DESC' => 'This should not grant ticket creation',
        ]
    );

    $role->permissions()->sync([$wrongPermission->LGL_ROW_ID]);

    $user = User::factory()->create([
        'USER_ROLE_ID' => $role->ROLE_ID,
        'DIV_ID' => $this->division->LGL_ROW_ID,
        'DEPT_ID' => $this->department->LGL_ROW_ID,
    ]);

    $this->actingAs($user);

    // User should NOT be able to access create page because they don't have tickets.create
    Volt::test('contracts.create')
        ->assertStatus(403);

    // Test route protection as well
    $response = $this->get(route('tickets.create'));
    $response->assertForbidden();

    // Now give the CORRECT permission
    $correctPermission = Permission::where('PERMISSION_CODE', 'tickets.create')->first();
    if (! $correctPermission) {
        $correctPermission = Permission::create([
            'PERMISSION_NAME' => 'Buat Ticket',
            'PERMISSION_CODE' => 'tickets.create',
            'PERMISSION_GROUP' => 'tickets',
            'PERMISSION_DESC' => 'Membuat ticket baru',
        ]);
    }

    $role->permissions()->sync([$correctPermission->LGL_ROW_ID]);
    $user->refresh();

    // Now it should work
    $response = $this->get(route('tickets.create'));
    $response->assertSuccessful();
});
