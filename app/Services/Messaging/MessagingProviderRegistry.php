<?php

namespace App\Services\Messaging;

use App\Contracts\Messaging\MessagingProviderContract;
use App\Enums\Messaging\MessagingFailureCategory;
use App\Enums\Messaging\MessagingProvider;
use App\Exceptions\Messaging\MessagingException;
use App\Services\Messaging\Providers\FakeMessagingProvider;
use App\Services\Messaging\Providers\MetaWhatsAppCloudProvider;

class MessagingProviderRegistry
{
    public function __construct(
        private readonly MetaWhatsAppCloudProvider $meta,
        private readonly FakeMessagingProvider $fake,
    ) {}

    public function resolve(?MessagingProvider $provider = null): MessagingProviderContract
    {
        $provider ??= $this->defaultProvider();

        return match ($provider) {
            MessagingProvider::Meta => $this->meta,
            MessagingProvider::Fake => $this->resolveFake(),
            default => throw new MessagingException('Unsupported messaging provider.', MessagingFailureCategory::ProviderUnavailable),
        };
    }

    public function defaultProvider(): MessagingProvider
    {
        if (app()->environment('testing')) {
            return MessagingProvider::Fake;
        }

        return MessagingProvider::from((string) config('messaging.default_provider', 'meta'));
    }

    private function resolveFake(): MessagingProviderContract
    {
        if (! app()->environment('testing')) {
            throw new MessagingException('Fake messaging provider is only available in testing.', MessagingFailureCategory::ProviderUnavailable);
        }

        return $this->fake;
    }
}
