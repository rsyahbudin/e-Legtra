<?php

declare(strict_types=1);

use App\Services\LegalDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('legal_docs');
    $this->service = app(LegalDocumentService::class);
});

test('createTicketFolders creates request and legal subdirectories', function () {
    $ticketNumber = 'TIC-FIN-26010001';

    $this->service->createTicketFolders($ticketNumber);

    Storage::disk('legal_docs')->assertExists("{$ticketNumber}/request");
    Storage::disk('legal_docs')->assertExists("{$ticketNumber}/legal");
});

test('createTicketFolders is idempotent', function () {
    $ticketNumber = 'TIC-FIN-26010001';

    $this->service->createTicketFolders($ticketNumber);
    $this->service->createTicketFolders($ticketNumber);

    Storage::disk('legal_docs')->assertExists("{$ticketNumber}/request");
    Storage::disk('legal_docs')->assertExists("{$ticketNumber}/legal");
});

test('uploadDocument stores file to request category', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('contract.pdf', 100);

    $path = $this->service->uploadDocument($file, $ticketNumber, 'request');

    expect($path)->toStartWith("{$ticketNumber}/request/");
    expect($path)->toEndWith('.pdf');
    Storage::disk('legal_docs')->assertExists($path);
});

test('uploadDocument stores file to legal category', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('review_notes.docx', 50);

    $path = $this->service->uploadDocument($file, $ticketNumber, 'legal');

    expect($path)->toStartWith("{$ticketNumber}/legal/");
    expect($path)->toEndWith('.docx');
    Storage::disk('legal_docs')->assertExists($path);
});

test('uploadDocument generates structured template filenames without original name', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('dangerous file (copy) [2].pdf', 100);

    $path = $this->service->uploadDocument($file, $ticketNumber, 'request');

    $filename = basename($path);

    // Should follow template: {TCKT_NO}_{category}_{timestamp}_{uuid}.{ext}
    expect($filename)->toStartWith('TIC_FIN_26010001_request_');
    expect($filename)->toEndWith('.pdf');

    // Should NOT contain original filename
    expect($filename)->not->toContain('dangerous');
    expect($filename)->not->toContain('copy');
    expect($filename)->not->toContain(' ');
    expect($filename)->not->toContain('(');
    expect($filename)->not->toContain('[');
});

test('uploadDocument throws for invalid category', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $file = UploadedFile::fake()->create('doc.pdf', 100);

    $this->service->uploadDocument($file, $ticketNumber, 'invalid');
})->throws(InvalidArgumentException::class);

test('getDocuments returns files in a category folder', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file1 = UploadedFile::fake()->create('doc1.pdf', 100);
    $file2 = UploadedFile::fake()->create('doc2.pdf', 100);

    $this->service->uploadDocument($file1, $ticketNumber, 'request');
    $this->service->uploadDocument($file2, $ticketNumber, 'request');

    $documents = $this->service->getDocuments($ticketNumber, 'request');

    expect($documents)->toHaveCount(2);
});

test('getDocuments returns empty array for empty folder', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $documents = $this->service->getDocuments($ticketNumber, 'legal');

    expect($documents)->toBeEmpty();
});

test('getDocuments throws for invalid category', function () {
    $this->service->getDocuments('TIC-FIN-26010001', 'invalid');
})->throws(InvalidArgumentException::class);

test('deleteDocument removes file from disk', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('to_delete.pdf', 100);
    $path = $this->service->uploadDocument($file, $ticketNumber, 'request');

    Storage::disk('legal_docs')->assertExists($path);

    $result = $this->service->deleteDocument($path);

    expect($result)->toBeTrue();
    Storage::disk('legal_docs')->assertMissing($path);
});

test('documentExists returns correct boolean', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('exists.pdf', 100);
    $path = $this->service->uploadDocument($file, $ticketNumber, 'request');

    expect($this->service->documentExists($path))->toBeTrue();
    expect($this->service->documentExists('fake/request/nope.pdf'))->toBeFalse();
});

test('getDocumentFullPath returns absolute path', function () {
    $ticketNumber = 'TIC-FIN-26010001';
    $this->service->createTicketFolders($ticketNumber);

    $file = UploadedFile::fake()->create('check_path.pdf', 100);
    $path = $this->service->uploadDocument($file, $ticketNumber, 'request');

    $fullPath = $this->service->getDocumentFullPath($path);

    expect($fullPath)->toContain($path);
    expect($fullPath)->toStartWith('/');
});
