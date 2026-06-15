<?php

namespace App\Enums\Billing;

enum PaymentEventType: string
{
    case OrderCreated = 'order_created';
    case CheckoutInitiated = 'checkout_initiated';
    case PaymentVerified = 'payment_verified';
    case PaymentCaptured = 'payment_captured';
    case PaymentFailed = 'payment_failed';
    case WebhookReceived = 'webhook_received';
    case WebhookVerified = 'webhook_verified';
    case DuplicateWebhookIgnored = 'duplicate_webhook_ignored';
    case SubscriptionActivationRequested = 'subscription_activation_requested';
    case SubscriptionActivationCompleted = 'subscription_activation_completed';
    case SubscriptionActivationFailed = 'subscription_activation_failed';
    case OrderExpired = 'order_expired';
    case VerificationRejected = 'verification_rejected';

    public function label(): string
    {
        return match ($this) {
            self::OrderCreated => 'Order created',
            self::CheckoutInitiated => 'Checkout initiated',
            self::PaymentVerified => 'Payment verified',
            self::PaymentCaptured => 'Payment captured',
            self::PaymentFailed => 'Payment failed',
            self::WebhookReceived => 'Webhook received',
            self::WebhookVerified => 'Webhook verified',
            self::DuplicateWebhookIgnored => 'Duplicate webhook ignored',
            self::SubscriptionActivationRequested => 'Subscription activation requested',
            self::SubscriptionActivationCompleted => 'Subscription activation completed',
            self::SubscriptionActivationFailed => 'Subscription activation failed',
            self::OrderExpired => 'Order expired',
            self::VerificationRejected => 'Verification rejected',
        };
    }
}
