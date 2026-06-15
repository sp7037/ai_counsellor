<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Messaging\MessagingProvider;
use App\Exceptions\Messaging\MessagingException;
use App\Services\Messaging\MessagingWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessagingWebhookController
{
    public function __invoke(Request $request, string $provider, MessagingWebhookService $webhooks): Response
    {
        if ($provider === 'fake' && ! app()->environment('testing')) {
            abort(404);
        }

        $providerEnum = match ($provider) {
            'meta' => MessagingProvider::Meta,
            'fake' => MessagingProvider::Fake,
            default => abort(404),
        };

        if ($request->isMethod('GET')) {
            return $this->verify($request, $webhooks);
        }

        return $this->receive($request, $webhooks, $providerEnum);
    }

    private function verify(Request $request, MessagingWebhookService $webhooks): Response
    {
        $mode = (string) $request->query('hub_mode', '');
        $verifyToken = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        try {
            $response = $webhooks->verifyChallenge($mode, $verifyToken, $challenge);
        } catch (MessagingException) {
            return response('Forbidden', 403);
        } catch (\Throwable) {
            return response('Verification failed', 500);
        }

        return response($response, 200);
    }

    private function receive(
        Request $request,
        MessagingWebhookService $webhooks,
        MessagingProvider $provider,
    ): Response {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        try {
            $result = $webhooks->handlePost(
                $rawBody,
                is_string($signature) ? $signature : null,
            );
        } catch (MessagingException) {
            return response('Forbidden', 403);
        } catch (\Throwable) {
            return response('Processing failed', 500);
        }

        return response(json_encode($result), 200, ['Content-Type' => 'application/json']);
    }
}
