<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\LegalDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('legal_docs');
    $this->service = app(LegalDocumentService::class);
});

test('authenticated user can preview a document inline', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
    $path = $this->service->uploadDocument($file, $ticketNumber, 'request');
    $filename = basename($path);

    $user = User::factory()->create();
    $user->role->update(['ROLE_SLUG' => 'super-admin']);

    $response = $this->actingAs($user)
        ->get(route('tickets.documents.preview', ['ticketNumber' => $ticketNumber, 'path' => $filename]));

    $response->assertSuccessful();
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
    expect($response->headers->get('Content-Disposition'))->toContain($filename);
});

test('authenticated user can download a document', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
    $path = $this->service->uploadDocument($file, $ticketNumber, 'request');
    $filename = basename($path);

    $user = User::factory()->create();
    $user->role->update(['ROLE_SLUG' => 'super-admin']);

    $response = $this->actingAs($user)
        ->get(route('tickets.documents.download', ['ticketNumber' => $ticketNumber, 'path' => $filename]));

    $response->assertSuccessful();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain($filename);
});

test('unauthenticated user cannot access documents', function () {
    $response = $this->get(route('tickets.documents.preview', ['ticketNumber' => 'TIC-FIN-26010001', 'path' => 'test.pdf']));

    $response->assertRedirect(route('login'));
});

test('requesting nonexistent document returns 404', function () {
    $user = User::factory()->create();
    $user->role->update(['ROLE_SLUG' => 'super-admin']);

    $response = $this->actingAs($user)
        ->get(route('tickets.documents.preview', ['ticketNumber' => 'TIC-FIN-26010001', 'path' => 'nonexistent.pdf']));

    $response->assertNotFound();
});
