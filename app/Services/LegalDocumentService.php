<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LegalDocumentService
{
    /** @var array<string> */
    private const ALLOWED_CATEGORIES = ['request', 'legal'];

    private const DISK = 'legal_docs';

    /**
     * Create the folder structure for a ticket.
     *
     * Creates: tickets/{ticket_number}/request/
     *          tickets/{ticket_number}/legal/
     */
    public function createTicketFolders(string $ticketNumber): void
    {
        $disk = Storage::disk(self::DISK);

        foreach (self::ALLOWED_CATEGORIES as $category) {
            $path = $this->buildPath($ticketNumber, $category);

            if (! $disk->exists($path)) {
                $disk->makeDirectory($path);
            }
        }
    }

    /**
     * Upload a document to a ticket's category folder.
     *
     * @return string The relative storage path of the uploaded file.
     */
    public function uploadDocument(UploadedFile $file, string $ticketNumber, string $category): string
    {
        $this->validateCategory($category);

        $directory = $this->buildPath($ticketNumber, $category);
        $safeFilename = $this->generateSafeFilename($file, $ticketNumber, $category);

        return $file->storeAs($directory, $safeFilename, self::DISK);
    }

    /**
     * List all documents in a ticket's category folder.
     *
     * @return array<string>
     */
    public function getDocuments(string $ticketNumber, string $category): array
    {
        $this->validateCategory($category);

        $directory = $this->buildPath($ticketNumber, $category);

        return Storage::disk(self::DISK)->files($directory);
    }

    /**
     * Delete a specific document by its relative path.
     */
    public function deleteDocument(string $path): bool
    {
        return Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Get the absolute filesystem path for a document (for download).
     */
    public function getDocumentFullPath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    /**
     * Check whether a document exists on disk.
     */
    public function documentExists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Build the directory path for a ticket category.
     */
    private function buildPath(string $ticketNumber, string $category): string
    {
        return "tickets/{$ticketNumber}/{$category}";
    }

    /**
     * Validate that the category is allowed.
     *
     * @throws InvalidArgumentException
     */
    private function validateCategory(string $category): void
    {
        if (! in_array($category, self::ALLOWED_CATEGORIES, true)) {
            $allowed = implode(', ', self::ALLOWED_CATEGORIES);

            throw new InvalidArgumentException(
                "Invalid document category '{$category}'. Allowed: {$allowed}."
            );
        }
    }

    /**
     * Generate a structured filename template.
     *
     * Format: {TCKT_NO}_{category}_{YmdHis}_{uuid8}.{ext}
     * Example: TIC-FIN-26010001_request_20260302134100_a1b2c3d4.pdf
     */
    private function generateSafeFilename(UploadedFile $file, string $ticketNumber, string $category): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Ymd_His');
        $uniqueSuffix = Str::substr(Str::uuid()->toString(), 0, 8);

        // Sanitise ticket number for filename (replace non-alphanumeric with underscore)
        $safeTicketNumber = preg_replace('/[^a-zA-Z0-9]/', '_', $ticketNumber);

        return "{$safeTicketNumber}_{$category}_{$timestamp}_{$uniqueSuffix}.{$extension}";
    }
}
