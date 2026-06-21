<?php

namespace App\Exceptions\Widget;

use Symfony\Component\HttpKernel\Exception\HttpException;

class WidgetGatewayDeniedException extends HttpException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'forbidden',
    ) {
        parent::__construct(403, $message);
    }
}
