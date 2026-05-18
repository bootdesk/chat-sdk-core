<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Cards;

enum TextStyle: string
{
    case Plain = 'plain';
    case Bold = 'bold';
    case Muted = 'muted';
}
