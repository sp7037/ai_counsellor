<?php

namespace App\Enums\Billing;

enum PaymentFailureCategory: string
{
    case CancelledByUser = 'cancelled_by_user';
    case SignatureInvalid = 'signature_invalid';
    case PaymentDeclined = 'payment_declined';
    case OrderExpired = 'order_expired';
    case ProviderUnavailable = 'provider_unavailable';
    case AmountMismatch = 'amount_mismatch';
    case CurrencyMismatch = 'currency_mismatch';
    case ModeMismatch = 'mode_mismatch';
    case Unknown = 'unknown';

    public function safeMessage(): string
    {
        return match ($this) {
            self::CancelledByUser => 'Payment was cancelled.',
            self::SignatureInvalid => 'Payment could not be verified. Please contact support if money was deducted.',
            self::PaymentDeclined => 'Payment was declined. Try again or use another method.',
            self::OrderExpired => 'This checkout session expired. Start a new payment.',
            self::ProviderUnavailable => 'Payment service is temporarily unavailable. Try again shortly.',
            self::AmountMismatch => 'Payment amount did not match the order. Contact support.',
            self::CurrencyMismatch => 'Payment currency did not match the order. Contact support.',
            self::ModeMismatch => 'Payment environment mismatch. Contact support.',
            self::Unknown => 'Payment could not be completed. Try again or contact support.',
        };
    }
}
