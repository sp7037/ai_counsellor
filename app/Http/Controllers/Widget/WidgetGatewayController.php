<?php

namespace App\Http\Controllers\Widget;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Http\Controllers\Controller;
use App\Http\Support\WidgetCorsResponse;
use App\Models\WidgetSession;
use App\Services\Billing\WidgetEntitlementService;
use App\Services\Configuration\WidgetPublicConfigService;
use App\Services\Conversations\ConversationHandoffService;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Leads\LeadCaptureService;
use App\Services\Widget\ConversationService;
use App\Services\Tenancy\TenantContext;
use App\Services\Widget\WidgetSessionService;
use App\Services\Widget\WidgetTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WidgetGatewayController extends Controller
{
    public function __construct(
        private readonly WidgetTenantResolver $tenantResolver,
        private readonly WidgetSessionService $sessionService,
        private readonly ConversationService $conversationService,
        private readonly LeadCaptureService $leadCapture,
        private readonly ConversationHandoffService $handoffService,
        private readonly ConversationMessageService $conversationMessages,
        private readonly WidgetPublicConfigService $publicConfigService,
        private readonly KnowledgeRetrievalContract $knowledgeRetrieval,
        private readonly WidgetEntitlementService $widgetEntitlements,
        private readonly WidgetCorsResponse $corsResponse,
        private readonly TenantContext $tenantContext,
    ) {}

    public function startSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widget_key' => ['required', 'string', 'max:64'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:12'],
            'fingerprint' => ['nullable', 'string', 'max:128'],
        ]);

        $resolved = $this->tenantResolver->resolve(
            $validated['widget_key'],
            $request->headers->get('Origin'),
            $request->headers->get('Referer'),
        );

        $availability = $this->widgetEntitlements->widgetAvailability($resolved['tenant']);

        if (! $availability['available']) {
            return $this->corsResponse->json($request, [
                'code' => $availability['code'],
                'message' => $availability['message'],
            ], 403, ['Cache-Control' => 'no-store, private']);
        }

        $result = $this->sessionService->start(
            $resolved['tenant'],
            $resolved['widget_key'],
            $resolved['origin_domain'],
            $validated['source_url'] ?? null,
            $validated['locale'] ?? null,
            $validated['fingerprint'] ?? null,
        );

        $publicConfig = $this->publicConfigService->forTenant($resolved['tenant']);

        return response()->json([
            'session_token' => $result['token'],
            'conversation_uuid' => $result['conversation']->uuid,
            'welcome_message' => $result['settings']->welcome_message,
            'offline_message' => $result['settings']->offline_message,
            'offline_form_enabled' => $result['settings']->offline_form_enabled,
            'expires_at' => $result['session']->expires_at->toIso8601String(),
            'configuration' => $publicConfig,
            'widget_mode' => $availability['mode'],
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widget_key' => ['required', 'string', 'max:64'],
        ]);

        $resolved = $this->tenantResolver->resolve(
            $validated['widget_key'],
            $request->headers->get('Origin'),
            $request->headers->get('Referer'),
        );

        // Establish tenant scope (same as the session middleware) so settings resolve safely.
        $this->tenantContext->setFromWidgetGateway($resolved['tenant']);
        $this->tenantContext->enforceIsolation();

        return $this->corsResponse->json($request, [
            'configuration' => $this->publicConfigService->chromeFor($resolved['tenant']),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function config(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');
        $settings = $this->sessionService->resolveSettings($session->tenant);

        $publicConfig = $this->publicConfigService->forTenant($session->tenant);

        return response()->json([
            'conversation_uuid' => $session->conversation->uuid,
            'mode' => $session->conversation->mode?->value ?? 'ai',
            'offline_message' => $settings->offline_message,
            'offline_form_enabled' => $settings->offline_form_enabled,
            'messages' => $this->conversationService->listMessages($session->conversation),
            'configuration' => $publicConfig,
        ]);
    }

    public function searchKnowledge(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:'.config('knowledge.max_search_query_length', 120)],
        ]);

        $results = $this->knowledgeRetrieval->searchPublished(
            $session->tenant,
            $validated['q'],
            config('knowledge.max_search_results', 20),
        );

        return response()->json([
            'results' => $results,
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.config('widget.max_message_length', 4000)],
            'request_id' => ['nullable', 'uuid'],
        ]);

        try {
            $result = $this->conversationService->addVisitorMessage(
                $session,
                $validated['body'],
                $validated['request_id'] ?? null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return response()->json([
            'visitor_message' => [
                'uuid' => $result['visitor_message']->uuid,
                'role' => $result['visitor_message']->role->value,
                'body' => $result['visitor_message']->body,
                'created_at' => $result['visitor_message']->created_at?->toIso8601String(),
            ],
            'reply' => $result['reply'] ? [
                'uuid' => $result['reply']->uuid,
                'role' => $result['reply']->role->value,
                'body' => $result['reply']->body,
                'created_at' => $result['reply']->created_at?->toIso8601String(),
                'sender_name' => $result['reply']->sender_display_name,
            ] : null,
            'mode' => $result['mode'] ?? $session->conversation->mode?->value ?? 'ai',
            'session_expires_at' => $session->expires_at->toIso8601String(),
        ]);
    }

    public function captureLead(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'service_interest' => ['nullable', 'string', 'max:255'],
            'enquiry_summary' => ['nullable', 'string', 'max:2000'],
            'capture_event_uuid' => ['required', 'uuid'],
        ]);

        $lead = $this->leadCapture->captureFromWidget(
            $session,
            $validated,
            $validated['capture_event_uuid'],
        );

        return response()->json([
            'message' => 'Thank you. Your enquiry has been received.',
            'lead_reference' => $lead->public_reference,
        ]);
    }

    public function requestHandoff(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');

        $validated = $request->validate([
            'handoff_request_uuid' => ['required', 'uuid'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'enquiry_summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->handoffService->requestFromWidget(
            $session,
            $validated['handoff_request_uuid'],
            $validated,
        );

        $ack = $result['acknowledgement'];

        return response()->json([
            'mode' => $result['conversation']->mode->value,
            'message' => $ack?->body ?? config('conversations.handoff_acknowledgement'),
            'acknowledgement' => $ack ? [
                'uuid' => $ack->uuid,
                'role' => $ack->role->value,
                'body' => $ack->body,
                'created_at' => $ack->created_at?->toIso8601String(),
            ] : null,
            'lead_reference' => ($result['lead'] ?? null)?->public_reference,
        ]);
    }

    public function pollMessages(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');

        $validated = $request->validate([
            'after' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('conversations.max_poll_messages', 50)],
        ]);

        if (! $this->widgetEntitlements->canPollHumanConversation($session->tenant)) {
            return response()->json([
                'mode' => $session->conversation->mode->value,
                'messages' => [],
            ])->header('Cache-Control', 'no-store, private');
        }

        $messages = $this->conversationMessages->listPublicMessages(
            $session->conversation,
            $validated['after'] ?? null,
            $validated['limit'] ?? config('conversations.max_poll_messages', 50),
        );

        return response()->json([
            'mode' => $session->conversation->fresh()->mode->value,
            'messages' => $messages,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function submitOffline(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');
        $settings = $this->sessionService->resolveSettings($session->tenant);

        if (! $settings->offline_form_enabled) {
            return response()->json(['message' => 'Offline form is disabled.'], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:'.config('widget.max_offline_message_length', 2000)],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $intake = $this->conversationService->submitOfflineIntake(
            $session,
            $validated['message'],
            $validated['name'] ?? null,
            $validated['email'] ?? null,
        );

        $lead = $this->leadCapture->captureFromOfflineIntake($session, [
            'full_name' => $validated['name'] ?? 'Visitor',
            'email' => $validated['email'] ?? null,
            'enquiry_summary' => $validated['message'],
        ], $intake->uuid);

        return response()->json([
            'message' => 'Your request has been received.',
            'intake' => [
                'uuid' => $intake->uuid,
                'created_at' => $intake->created_at?->toIso8601String(),
            ],
            'lead_reference' => $lead->public_reference,
        ]);
    }
}
