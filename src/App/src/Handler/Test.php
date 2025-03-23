<?php

declare(strict_types=1);

namespace App\Handler;

enum Test: string
{
    case Metadata     = 'metadata';
    case TableGateway = 'tablegateway';
}
