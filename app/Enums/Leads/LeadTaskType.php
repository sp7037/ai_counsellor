<?php

namespace App\Enums\Leads;

enum LeadTaskType: string
{
    case Call = 'call';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case DocumentCollection = 'document_collection';
    case Counselling = 'counselling';
    case PaymentFollowup = 'payment_followup';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Whatsapp => 'WhatsApp',
            self::Email => 'Email',
            self::DocumentCollection => 'Document collection',
            self::Counselling => 'Counselling',
            self::PaymentFollowup => 'Payment follow-up',
            self::Other => 'Other',
        };
    }
}
