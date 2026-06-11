<?php

namespace App\Http\Controllers;

use App\Models\UploadedDocument;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Stream the active FAQ document inline (opens in a new browser tab).
     */
    public function faq()
    {
        $document = UploadedDocument::activeForKey('faq');

        if (! $document) {
            abort(404, 'No FAQ document is available at the moment.');
        }

        return $this->streamInline($document);
    }

    /**
     * Stream a specific document inline. Used by the admin Uploads tab to preview a file.
     */
    public function show(UploadedDocument $document, ?string $filename = null)
    {
        // Clients may only view active documents; admins may preview any.
        if (! $document->is_active && ! auth()->user()->hasAdminAccess()) {
            abort(404, 'Document not found.');
        }

        return $this->streamInline($document);
    }

    /**
     * Build an inline (in-browser) response for the given document.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function streamInline(UploadedDocument $document)
    {
        if (! Storage::disk('local')->exists($document->file_path)) {
            \Log::warning('Uploaded document missing from storage', [
                'document_id' => $document->id,
                'file_path' => $document->file_path,
            ]);

            abort(404, 'The document is missing from storage. Please contact the administrator.');
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type' => $document->mime_type ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.addslashes($document->file_name).'"',
            ]
        );
    }
}
