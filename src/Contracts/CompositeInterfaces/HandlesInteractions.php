<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts\CompositeInterfaces;

use BootDesk\ChatSDK\Core\Contracts\HandlesActions;
use BootDesk\ChatSDK\Core\Contracts\HandlesReactions;
use BootDesk\ChatSDK\Core\Contracts\HandlesSlashCommands;

interface HandlesInteractions extends HandlesActions, HandlesReactions, HandlesSlashCommands {}
