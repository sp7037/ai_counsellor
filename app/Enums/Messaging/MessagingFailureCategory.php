<?php

namespace App\Enums\Messaging;

enum MessagingFailureCategory: string
{
    case AuthenticationFailed = 'authentication_failed';
    case PermissionDenied = 'permission_denied';
    case RateLimited = 'rate_limited';
    case InvalidRecipient = 'invalid_recipient';
    case SessionWindowClosed = 'session_window_closed';
    case TemplateRequired = 'template_required';
    case TemplateRejected = 'template_rejected';
    case ProviderUnavailable = 'provider_unavailable';
    case Timeout = 'timeout';
    case MalformedResponse = 'malformed_response';
    case SignatureInvalid = 'signature_invalid';
    case Unknown = 'unknown';

    public function safeMessage(): string
    {
        return match ($this) {
            self::AuthenticationFailed => 'Messaging authentication failed.',
            self::PermissionDenied => 'Messaging permission denied.',
            self::RateLimited => 'Messaging rate limit reached.',
            self::InvalidRecipient => 'Recipient is not valid for messaging.',
            self::SessionWindowClosed => 'Free-form reply is not permitted outside the service window.',
            self::TemplateRequired => 'An approved template is required to message this contact.',
            self::TemplateRejected => 'Template was rejected by the provider.',
            self::ProviderUnavailable => 'Messaging service is temporarily unavailable.',
            self::Timeout => 'Messaging request timed out.',
            self::MalformedResponse => 'Messaging provider returned an invalid response.',
            self::SignatureInvalid => 'Webhook signature verification failed.',
            self::Unknown => 'Messaging operation failed.',
        };
    }
}
