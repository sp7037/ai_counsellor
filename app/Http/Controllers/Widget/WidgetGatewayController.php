<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\WidgetSession;
use App\Services\Widget\ConversationService;
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

        $result = $this->sessionService->start(
            $resolved['tenant'],
            $resolved['widget_key'],
            $resolved['origin_domain'],
            $validated['source_url'] ?? null,
            $validated['locale'] ?? null,
            $validated['fingerprint'] ?? null,
        );

        return response()->json([
            'session_token' => $result['token'],
            'conversation_uuid' => $result['conversation']->uuid,
            'welcome_message' => $result['settings']->welcome_message,
            'offline_message' => $result['settings']->offline_message,
            'offline_form_enabled' => $result['settings']->offline_form_enabled,
            'expires_at' => $result['session']->expires_at->toIso8601String(),
        ]);
    }

    public function config(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');
        $settings = $this->sessionService->resolveSettings($session->tenant);

        return response()->json([
            'conversation_uuid' => $session->conversation->uuid,
            'offline_message' => $settings->offline_message,
            'offline_form_enabled' => $settings->offline_form_enabled,
            'messages' => $this->conversationService->listMessages($session->conversation),
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        /** @var WidgetSession $session */
        $session = $request->attributes->get('widget_session');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.config('widget.max_message_length', 4000)],
        ]);

        try {
            $result = $this->conversationService->addVisitorMessage($session, $validated['body']);
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
            'reply' => [
                'uuid' => $result['system_reply']->uuid,
                'role' => $result['system_reply']->role->value,
                'body' => $result['system_reply']->body,
                'created_at' => $result['system_reply']->created_at?->toIso8601String(),
            ],
        ]);
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

        return response()->json([
            'message' => 'Your request has been received.',
            'intake' => [
                'uuid' => $intake->uuid,
                'created_at' => $intake->created_at?->toIso8601String(),
            ],
        ]);
    }
}
