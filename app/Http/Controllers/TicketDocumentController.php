<?php

namespace App\Http\Controllers;

use App\Services\LegalDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketDocumentController
{
    public function __construct(
        private LegalDocumentService $documentService,
    ) {}

    /**
     * Resolve the full relative path for the document.
     * Backwards compatibility: If only filename is given, it checks request/ then legal/.
     */
    private function resolveFullPath(string $ticketNumber, string $path): string
    {
        $fullPath = "{$ticketNumber}/{$path}";

        if (! Str::contains($path, '/')) {
            if ($this->documentService->documentExists("{$ticketNumber}/request/{$path}")) {
                return "{$ticketNumber}/request/{$path}";
            } elseif ($this->documentService->documentExists("{$ticketNumber}/legal/{$path}")) {
                return "{$ticketNumber}/legal/{$path}";
            }
        }

        return $fullPath;
    }

    /**
     * Preview a document inline (PDF opens in browser, images display inline).
     */
    public function preview(Request $request, string $ticketNumber, string $path): BinaryFileResponse
    {
        $fullPath = $this->resolveFullPath($ticketNumber, $path);

        abort_unless($this->documentService->documentExists($fullPath), 404, 'Document not found.');

        $absolutePath = $this->documentService->getDocumentFullPath($fullPath);
        $filename = basename($path);

        return response()->file($absolutePath, [
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Force download a document.
     */
    public function download(Request $request, string $ticketNumber, string $path): BinaryFileResponse
    {
        $fullPath = $this->resolveFullPath($ticketNumber, $path);

        abort_unless($this->documentService->documentExists($fullPath), 404, 'Document not found.');

        $absolutePath = $this->documentService->getDocumentFullPath($fullPath);
        $filename = basename($path);

        return response()->download($absolutePath, $filename);
    }
}
