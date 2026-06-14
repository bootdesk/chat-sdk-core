<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Support;

class EmojiResolver
{
    private const EMOJI_PLACEHOLDER_REGEX = '/\{\{emoji:([a-z0-9_]+)\}\}/i';

    private static ?self $default = null;

    private array $emojiMap;

    private array $slackToNormalized = [];

    private array $gchatToNormalized = [];

    private array $githubToNormalized = [];

    public function __construct(?array $customMap = null, ?string $jsonPath = null)
    {
        $jsonPath ??= __DIR__.'/../../data/emoji.json';

        $defaultMap = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($defaultMap)) {
            $defaultMap = [];
        }

        $this->emojiMap = $customMap !== null
            ? array_merge($defaultMap, $customMap)
            : $defaultMap;

        $this->buildReverseMaps();
    }

    public static function default(): self
    {
        if (! self::$default instanceof self) {
            self::$default = new self;
        }

        return self::$default;
    }

    private function buildReverseMaps(): void
    {
        $this->slackToNormalized = [];
        $this->gchatToNormalized = [];
        $this->githubToNormalized = [];

        foreach ($this->emojiMap as $normalized => $formats) {
            $slackFormats = is_array($formats['slack'] ?? null)
                ? $formats['slack']
                : [$formats['slack'] ?? ''];

            foreach ($slackFormats as $slack) {
                $this->slackToNormalized[mb_strtolower($slack)] = $normalized;
            }

            $gchatFormats = is_array($formats['gchat'] ?? null)
                ? $formats['gchat']
                : [$formats['gchat'] ?? ''];

            foreach ($gchatFormats as $gchat) {
                $this->gchatToNormalized[$gchat] = $normalized;
            }

            $githubFormats = is_array($formats['github'] ?? null)
                ? $formats['github']
                : [$formats['github'] ?? ''];

            foreach ($githubFormats as $github) {
                $this->githubToNormalized[mb_strtolower($github)] = $normalized;
            }
        }
    }

    public function fromSlack(string $slackEmoji): string
    {
        $cleaned = preg_replace('/^:|:$/', '', mb_strtolower($slackEmoji));

        return $this->slackToNormalized[$cleaned] ?? $slackEmoji;
    }

    public function toSlack(string $emoji): string
    {
        $formats = $this->emojiMap[$emoji] ?? null;

        if ($formats === null) {
            return $emoji;
        }

        $slack = $formats['slack'] ?? null;

        if ($slack === null) {
            return $emoji;
        }

        return is_array($slack) ? $slack[0] : $slack;
    }

    public function fromGChat(string $emoji): string
    {
        return $this->gchatToNormalized[$emoji] ?? $emoji;
    }

    public function toGChat(string $emoji): string
    {
        $formats = $this->emojiMap[$emoji] ?? null;

        if ($formats === null) {
            return $emoji;
        }

        $gchat = $formats['gchat'] ?? null;

        if ($gchat === null) {
            return $emoji;
        }

        return is_array($gchat) ? $gchat[0] : $gchat;
    }

    public function fromTeams(string $teamsReaction): string
    {
        $teamsMap = [
            'like' => 'thumbs_up',
            'heart' => 'heart',
            'laugh' => 'laugh',
            'surprised' => 'surprised',
            'sad' => 'sad',
            'angry' => 'angry',
        ];

        return $teamsMap[$teamsReaction] ?? $teamsReaction;
    }

    public function toDiscord(string $emoji): string
    {
        return $this->toGChat($emoji);
    }

    public function fromGithub(string $githubEmoji): string
    {
        return $this->githubToNormalized[mb_strtolower($githubEmoji)] ?? $githubEmoji;
    }

    public function toGithub(string $emoji): string
    {
        $github = $this->resolveGithubFormat($emoji);

        if ($github !== null) {
            return $github;
        }

        return '+1';
    }

    private function resolveGithubFormat(string $emoji): ?string
    {
        $formats = $this->emojiMap[$emoji] ?? null;

        if ($formats !== null && isset($formats['github'])) {
            $github = $formats['github'];

            return is_array($github) ? $github[0] : $github;
        }

        $normalized = $this->slackToNormalized[mb_strtolower(preg_replace('/^:|:$/', '', $emoji))]
            ?? $this->gchatToNormalized[$emoji]
            ?? null;

        if ($normalized !== null) {
            $formats = $this->emojiMap[$normalized] ?? null;

            if ($formats !== null && isset($formats['github'])) {
                $github = $formats['github'];

                return is_array($github) ? $github[0] : $github;
            }
        }

        return null;
    }

    public function matches(string $rawEmoji, string $normalized): bool
    {
        $formats = $this->emojiMap[$normalized] ?? null;

        if ($formats === null) {
            return $rawEmoji === $normalized;
        }

        $slackFormats = is_array($formats['slack'] ?? null)
            ? $formats['slack']
            : [$formats['slack'] ?? ''];

        $gchatFormats = is_array($formats['gchat'] ?? null)
            ? $formats['gchat']
            : [$formats['gchat'] ?? ''];

        $githubFormats = is_array($formats['github'] ?? null)
            ? $formats['github']
            : [$formats['github'] ?? ''];

        $cleanedRaw = preg_replace('/^:|:$/', '', mb_strtolower($rawEmoji));

        foreach ($slackFormats as $slack) {
            if (mb_strtolower($slack) === $cleanedRaw) {
                return true;
            }
        }

        if (in_array($rawEmoji, $gchatFormats, true)) {
            return true;
        }

        foreach ($githubFormats as $github) {
            if (mb_strtolower($github) === $cleanedRaw) {
                return true;
            }
        }

        return false;
    }

    public function extend(array $customMap): void
    {
        foreach ($customMap as $name => $formats) {
            $this->emojiMap[$name] = $formats;
        }

        $this->buildReverseMaps();
    }

    public static function convertPlaceholders(
        string $text,
        string $platform,
        ?self $resolver = null,
    ): string {
        $resolver ??= self::default();

        return preg_replace_callback(
            self::EMOJI_PLACEHOLDER_REGEX,
            function (array $matches) use ($platform, $resolver): string {
                $emojiName = $matches[1];

                return match ($platform) {
                    'slack' => ':'.$resolver->toSlack($emojiName).':',
                    'gchat', 'teams', 'discord', 'messenger', 'github', 'linear', 'whatsapp' => $resolver->toGChat($emojiName),
                    default => $resolver->toGChat($emojiName),
                };
            },
            $text,
        );
    }
}
