<div class="grid gap-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="tenant_id" label="Tenant">
            <option value="">All tenants</option>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
            @endforeach
        </flux:select>
        @if (property_exists($this, 'status'))
            <flux:select wire:model.live="status" label="Status">
                <option value="">All</option>
                <option value="processing">Processing</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
            </flux:select>
        @endif
        <flux:input wire:model.live="from" type="date" label="From" />
        <flux:input wire:model.live="to" type="date" label="To" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-900 text-left text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Run</th>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Provider</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Tokens</th>
                    <th class="px-4 py-3">Error</th>
                    <th class="px-4 py-3">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800 text-zinc-200">
                @forelse ($runs as $run)
                    <tr class="cursor-pointer hover:bg-zinc-900" wire:click="showRun({{ $run->id }})">
                        <td class="px-4 py-3 font-mono text-xs">#{{ $run->id }}</td>
                        <td class="px-4 py-3">{{ $run->tenant?->name }}</td>
                        <td class="px-4 py-3">{{ $run->provider }} / {{ $run->model }}</td>
                        <td class="px-4 py-3">{{ $run->status }}</td>
                        <td class="px-4 py-3">{{ $run->total_tokens ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $run->error_category ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $run->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-zinc-500">No AI runs match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $runs->links() }}

    @if ($runDetail ?? false)
        <div class="fixed inset-0 z-50 flex justify-end bg-black/50" wire:click="closeRun">
            <div class="h-full w-full max-w-xl overflow-y-auto border-l border-zinc-800 bg-zinc-950 p-6" wire:click.stop>
                <div class="flex items-center justify-between">
                    <flux:heading size="md">Run #{{ $runDetail['id'] }}</flux:heading>
                    <flux:button wire:click="closeRun" variant="ghost" size="sm">Close</flux:button>
                </div>
                <dl class="mt-4 grid gap-2 text-sm text-zinc-300">
                    @foreach ($runDetail as $key => $value)
                        @if (! is_array($value))
                            <div class="flex justify-between gap-4 border-b border-zinc-900 py-2">
                                <dt class="text-zinc-500">{{ str_replace('_', ' ', $key) }}</dt>
                                <dd class="text-right font-mono text-xs">{{ $value ?? '—' }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        </div>
    @endif
</div>
