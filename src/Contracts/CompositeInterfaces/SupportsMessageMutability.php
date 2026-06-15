<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts\CompositeInterfaces;

use BootDesk\ChatSDK\Core\Contracts\SupportsDeleteMessages;
use BootDesk\ChatSDK\Core\Contracts\SupportsEditMessages;
use BootDesk\ChatSDK\Core\Contracts\SupportsEditThread;

interface SupportsMessageMutability extends SupportsDeleteMessages, SupportsEditMessages, SupportsEditThread {}
