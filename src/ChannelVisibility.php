<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

enum ChannelVisibility: string
{
    case Private = 'private';
    case Workspace = 'workspace';
    case External = 'external';
    case Unknown = 'unknown';
}
