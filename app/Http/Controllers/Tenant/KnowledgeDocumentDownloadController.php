<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeDocumentDownloadController extends Controller
{
    public function __invoke(KnowledgeDocument $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        if (! Storage::disk('local')->exists($document->storage_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->display_name,
            ['Content-Type' => $document->mime_type],
        );
    }
}
