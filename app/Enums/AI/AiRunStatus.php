<?php

namespace App\Enums\AI;

enum AiRunStatus: string
{
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
}
