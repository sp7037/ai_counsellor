<?php

use App\Models\AiRun;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\Platform\PlatformAiOperationsService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.platform')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tenant_id = '';

    public string $status = 'failed';

    #[Url]
    public string $provider = '';

    #[Url]
    public string $credential_source = '';

    #[Url]
    public string $error_category = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public ?int $selectedRunId = null;

    public function showRun(int $runId): void
    {
        Gate::authorize('view', AiRun::query()->findOrFail($runId));
        $this->selectedRunId = $runId;
    }

    public function closeRun(): void
    {
        $this->selectedRunId = null;
    }

    public function with(PlatformAiOperationsService $operations): array
    {
        $filters = array_filter([
            'tenant_id' => $this->tenant_id !== '' ? (int) $this->tenant_id : null,
            'status' => $this->status,
            'provider' => $this->provider !== '' ? $this->provider : null,
            'credential_source' => $this->credential_source !== '' ? $this->credential_source : null,
            'error_category' => $this->error_category !== '' ? $this->error_category : null,
            'from' => $this->from !== '' ? $this->from : null,
            'to' => $this->to !== '' ? $this->to : null,
        ], fn ($value) => $value !== null);

        $runs = $operations->paginate($filters);
        $runDetail = null;

        if ($this->selectedRunId !== null) {
            $run = AiRun::query()->find($this->selectedRunId);
            if ($run !== null) {
                Gate::authorize('view', $run);
                $runDetail = $operations->safeRunDetail($run);
            }
        }

        return [
            'runs' => $runs,
            'runDetail' => $runDetail,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<x-slot:heading>Failed AI runs</x-slot:heading>

@include('livewire.platform.ai-operations._table')
