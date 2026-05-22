<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

final readonly class Author
{
    /** @var LocalizationValue[] */
    public array $localizations;

    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $email = null,
        public bool $isMe = false,
        public bool $isBot = false,
        public ?string $profilePicture = null,
        LocalizationValue ...$localizations,
    ) {
        $this->localizations = $localizations;
    }

    public function getLocalization(LocalizationType $type): ?string
    {
        foreach ($this->localizations as $loc) {
            if ($loc->type === $type) {
                return $loc->value;
            }
        }

        return null;
    }

    public function hasLocalization(LocalizationType $type): bool
    {
        return $this->getLocalization($type) !== null;
    }

    public function withLocalizations(LocalizationValue ...$localizations): self
    {
        return new self(
            $this->id,
            $this->name,
            $this->email,
            $this->isMe,
            $this->isBot,
            $this->profilePicture,
            ...array_merge($this->localizations, $localizations),
        );
    }
}
