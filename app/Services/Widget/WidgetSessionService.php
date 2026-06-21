<?php

namespace App\Services\Widget;

use App\Enums\Conversations\ConversationChannel;
use App\Enums\Conversations\ConversationStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantWidgetSettings;
use App\Models\Visitor;
use App\Models\WidgetKey;
use App\Models\WidgetSession;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WidgetSessionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function start(
        Tenant $tenant,
        WidgetKey $widgetKey,
        string $originDomain,
        ?string $sourceUrl = null,
        ?string $locale = null,
        ?string $fingerprint = null,
    ): array {
        return DB::transaction(function () use ($tenant, $widgetKey, $originDomain, $sourceUrl, $locale, $fingerprint): array {
            $this->tenantContext->setFromWidgetGateway($tenant);
            $this->tenantContext->enforceIsolation();

            $visitor = Visitor::query()->create([
                'fingerprint_hash' => $fingerprint ? hash('sha256', $fingerprint) : null,
            ]);

            $conversation = Conversation::query()->create([
                'visitor_id' => $visitor->id,
                'channel' => ConversationChannel::Widget->value,
                'status' => ConversationStatus::Open->value,
                'source_url' => $sourceUrl,
                'origin_domain' => $originDomain,
                'locale' => $locale,
                'started_at' => now(),
            ]);

            $plainToken = Str::random(64);

            $session = WidgetSession::query()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'visitor_id' => $visitor->id,
                'widget_key_id' => $widgetKey->id,
                'origin_domain' => $originDomain,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes(max(5, (int) config('widget.session_ttl_minutes', 120))),
            ]);

            $settings = $this->resolveSettings($tenant);

            if ($settings->welcome_message) {
                Message::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::System->value,
                    'body' => $settings->welcome_message,
                ]);
            }

            return [
                'session' => $session,
                'conversation' => $conversation,
                'token' => $plainToken,
                'settings' => $settings,
            ];
        });
    }

    public function findByToken(string $plainToken): ?WidgetSession
    {
        $token = trim($plainToken);

        if ($token === '') {
            return null;
        }

        return WidgetSession::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }

    public function touch(WidgetSession $session): void
    {
        $ttlMinutes = max(5, (int) config('widget.session_ttl_minutes', 120));

        $session->update([
            'last_used_at' => now(),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }

    public function resolveSettings(Tenant $tenant): TenantWidgetSettings
    {
        return TenantWidgetSettings::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'welcome_message' => (string) config('widget.default_welcome_message', 'Hello! I am your AI counsellor. Ask me about services, admission, eligibility, fees, documents, or counselling support.'),
                'offline_message' => 'We are currently offline. Leave your details and we will get back to you.',
                'offline_form_enabled' => true,
            ],
        );
    }
}
