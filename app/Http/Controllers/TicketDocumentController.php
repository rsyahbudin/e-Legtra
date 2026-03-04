<?php

namespace App\Http\Controllers;

use App\Services\LegalDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketDocumentController
{
    public function __construct(
        private LegalDocumentService $documentService,
    ) {}

    /**
     * Preview a document inline (PDF opens in browser, images display inline).
     */
    public function preview(Request $request, string $ticketNumber, string $path): BinaryFileResponse
    {
        $fullPath = "tickets/{$ticketNumber}/request/{$path}";

        abort_unless($this->documentService->documentExists($fullPath), 404, 'Document not found.');

        $absolutePath = $this->documentService->getDocumentFullPath($fullPath);

        return response()->file($absolutePath, [
            'Content-Disposition' => "inline; filename={$path}",
        ]);
    }

    /**
     * Force download a document.
     */
    public function download(Request $request, string $ticketNumber, string $path): BinaryFileResponse
    {
        $fullPath = "tickets/{$ticketNumber}/request/{$path}";

        abort_unless($this->documentService->documentExists($fullPath), 404, 'Document not found.');

        $absolutePath = $this->documentService->getDocumentFullPath($fullPath);

        return response()->download($absolutePath, $path);
    }
}
