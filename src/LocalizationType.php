<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

enum LocalizationType: string
{
    case Locale = 'locale';
    case Language = 'language';
    case Timezone = 'timezone';
}
