<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\MessagingContact;
use Carbon\CarbonInterface;

class MessagingSessionWindowService
{
    public function windowHours(): int
    {
        return max(1, (int) config('messaging.service_window_hours', 24));
    }

    public function isWithinWindow(MessagingContact $contact): bool
    {
        $expiresAt = $this->expiresAt($contact);

        return $expiresAt !== null && $expiresAt->isFuture();
    }

    public function isWithinWindowForConversation(Conversation $conversation): bool
    {
        $contact = $conversation->messagingContact;

        if ($contact === null) {
            return false;
        }

        return $this->isWithinWindow($contact);
    }

    public function expiresAt(MessagingContact $contact): ?CarbonInterface
    {
        if ($contact->last_inbound_at === null) {
            return null;
        }

        return $contact->last_inbound_at->copy()->addHours($this->windowHours());
    }
}
