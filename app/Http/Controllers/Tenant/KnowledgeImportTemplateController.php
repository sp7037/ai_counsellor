<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Billing\PlanFeature;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Billing\EntitlementResolver;
use App\Services\Knowledge\KnowledgeImportTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeImportTemplateController extends Controller
{
    public function __invoke(
        Tenant $tenant,
        string $type,
        KnowledgeImportTemplateService $templates,
        EntitlementResolver $entitlements,
    ): StreamedResponse {
        $this->authorize('manageTenantKnowledge', $tenant);

        abort_unless($entitlements->check($tenant, PlanFeature::KnowledgeBase)->isAllowed(), 403);

        $content = $templates->content($type);
        $filename = $templates->filenames()[$type] ?? 'knowledge-import-template.csv';

        return response()->streamDownload(
            static fn () => print($content),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
