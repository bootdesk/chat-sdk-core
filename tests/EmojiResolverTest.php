<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Support\EmojiResolver;
use BootDesk\ChatSDK\Core\Support\EmojiValue;
use PHPUnit\Framework\TestCase;

class EmojiResolverTest extends TestCase
{
    public function test_from_slack_converts_to_normalized(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('thumbs_up', $resolver->fromSlack('+1'));
        $this->assertSame('thumbs_up', $resolver->fromSlack('thumbsup'));
        $this->assertSame('thumbs_down', $resolver->fromSlack('-1'));
        $this->assertSame('heart', $resolver->fromSlack('heart'));
        $this->assertSame('fire', $resolver->fromSlack('fire'));
    }

    public function test_from_slack_handles_colons(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('thumbs_up', $resolver->fromSlack(':+1:'));
        $this->assertSame('fire', $resolver->fromSlack(':fire:'));
    }

    public function test_from_slack_case_insensitive(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('fire', $resolver->fromSlack('FIRE'));
        $this->assertSame('heart', $resolver->fromSlack('Heart'));
    }

    public function test_from_slack_returns_raw_if_unmapped(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('custom_emoji', $resolver->fromSlack('custom_emoji'));
    }

    public function test_to_slack_converts_normalized_to_slack(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('+1', $resolver->toSlack('thumbs_up'));
        $this->assertSame('fire', $resolver->toSlack('fire'));
        $this->assertSame('heart', $resolver->toSlack('heart'));
    }

    public function test_to_slack_returns_raw_if_unmapped(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('custom', $resolver->toSlack('custom'));
    }

    public function test_from_gchat_converts_to_normalized(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('thumbs_up', $resolver->fromGChat('👍'));
        $this->assertSame('thumbs_down', $resolver->fromGChat('👎'));
        $this->assertSame('heart', $resolver->fromGChat('❤️'));
        $this->assertSame('fire', $resolver->fromGChat('🔥'));
        $this->assertSame('rocket', $resolver->fromGChat('🚀'));
    }

    public function test_from_gchat_handles_unicode_variants(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('heart', $resolver->fromGChat('❤'));
        $this->assertSame('heart', $resolver->fromGChat('❤️'));
        $this->assertSame('check', $resolver->fromGChat('✅'));
        $this->assertSame('check', $resolver->fromGChat('✔️'));
    }

    public function test_from_gchat_returns_raw_if_unmapped(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('🦄', $resolver->fromGChat('🦄'));
    }

    public function test_to_gchat_converts_normalized_to_unicode(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('👍', $resolver->toGChat('thumbs_up'));
        $this->assertSame('🔥', $resolver->toGChat('fire'));
        $this->assertSame('🚀', $resolver->toGChat('rocket'));
    }

    public function test_to_gchat_returns_raw_if_unmapped(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('custom', $resolver->toGChat('custom'));
    }

    public function test_from_teams_converts_to_normalized(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('thumbs_up', $resolver->fromTeams('like'));
        $this->assertSame('heart', $resolver->fromTeams('heart'));
        $this->assertSame('laugh', $resolver->fromTeams('laugh'));
        $this->assertSame('surprised', $resolver->fromTeams('surprised'));
        $this->assertSame('sad', $resolver->fromTeams('sad'));
        $this->assertSame('angry', $resolver->fromTeams('angry'));
    }

    public function test_from_teams_returns_raw_if_unmapped(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('custom_reaction', $resolver->fromTeams('custom_reaction'));
    }

    public function test_to_discord_returns_unicode(): void
    {
        $resolver = new EmojiResolver;
        $this->assertSame('👍', $resolver->toDiscord('thumbs_up'));
        $this->assertSame('🔥', $resolver->toDiscord('fire'));
    }

    public function test_matches_slack_format(): void
    {
        $resolver = new EmojiResolver;
        $this->assertTrue($resolver->matches('+1', 'thumbs_up'));
        $this->assertTrue($resolver->matches('thumbsup', 'thumbs_up'));
        $this->assertTrue($resolver->matches(':+1:', 'thumbs_up'));
        $this->assertTrue($resolver->matches('fire', 'fire'));
    }

    public function test_matches_gchat_format(): void
    {
        $resolver = new EmojiResolver;
        $this->assertTrue($resolver->matches('👍', 'thumbs_up'));
        $this->assertTrue($resolver->matches('🔥', 'fire'));
        $this->assertTrue($resolver->matches('❤️', 'heart'));
    }

    public function test_matches_returns_false_for_different_emoji(): void
    {
        $resolver = new EmojiResolver;
        $this->assertFalse($resolver->matches('+1', 'thumbs_down'));
        $this->assertFalse($resolver->matches('👍', 'fire'));
    }

    public function test_matches_unmapped_emoji_by_equality(): void
    {
        $resolver = new EmojiResolver;
        $this->assertTrue($resolver->matches('custom', 'custom'));
        $this->assertFalse($resolver->matches('custom', 'other'));
    }

    public function test_extend_adds_new_mappings(): void
    {
        $resolver = new EmojiResolver;
        $resolver->extend([
            'unicorn' => ['slack' => ['unicorn_face'], 'gchat' => ['🦄']],
        ]);

        $this->assertSame('unicorn', $resolver->fromSlack('unicorn_face'));
        $this->assertSame('unicorn', $resolver->fromGChat('🦄'));
        $this->assertSame('unicorn_face', $resolver->toSlack('unicorn'));
        $this->assertSame('🦄', $resolver->toGChat('unicorn'));
    }

    public function test_extend_overrides_existing(): void
    {
        $resolver = new EmojiResolver;
        $resolver->extend([
            'fire' => ['slack' => ['flames'], 'gchat' => ['🔥']],
        ]);

        $this->assertSame('fire', $resolver->fromSlack('flames'));
        $this->assertSame('flames', $resolver->toSlack('fire'));
    }

    public function test_default_resolver_is_singleton(): void
    {
        $r1 = EmojiResolver::default();
        $r2 = EmojiResolver::default();
        $this->assertSame($r1, $r2);
        $this->assertSame('thumbs_up', $r1->fromSlack('+1'));
    }

    public function test_convert_placeholders_slack(): void
    {
        $resolver = new EmojiResolver;
        $result = EmojiResolver::convertPlaceholders(
            'Thanks! {{emoji:thumbs_up}} Great work! {{emoji:fire}}',
            'slack',
            $resolver,
        );
        $this->assertSame('Thanks! :+1: Great work! :fire:', $result);
    }

    public function test_convert_placeholders_gchat(): void
    {
        $resolver = new EmojiResolver;
        $result = EmojiResolver::convertPlaceholders(
            'Thanks! {{emoji:thumbs_up}} Great work! {{emoji:fire}}',
            'gchat',
            $resolver,
        );
        $this->assertSame('Thanks! 👍 Great work! 🔥', $result);
    }

    public function test_convert_placeholders_unknown_emoji(): void
    {
        $resolver = new EmojiResolver;
        $result = EmojiResolver::convertPlaceholders(
            'Check this {{emoji:unknown_emoji}}!',
            'slack',
            $resolver,
        );
        $this->assertSame('Check this :unknown_emoji:!', $result);
    }

    public function test_convert_placeholders_no_emoji(): void
    {
        $resolver = new EmojiResolver;
        $result = EmojiResolver::convertPlaceholders(
            'Just a regular message',
            'slack',
            $resolver,
        );
        $this->assertSame('Just a regular message', $result);
    }

    public function test_convert_placeholders_discord(): void
    {
        $resolver = new EmojiResolver;
        $result = EmojiResolver::convertPlaceholders(
            'Thanks! {{emoji:thumbs_up}} Great work! {{emoji:fire}}',
            'discord',
            $resolver,
        );
        $this->assertSame('Thanks! 👍 Great work! 🔥', $result);
    }

    public function test_emoji_value_singleton(): void
    {
        $e1 = EmojiValue::get('thumbs_up');
        $e2 = EmojiValue::get('thumbs_up');
        $this->assertSame($e1, $e2);
    }

    public function test_emoji_value_name(): void
    {
        $e = EmojiValue::get('fire');
        $this->assertSame('fire', $e->name);
    }

    public function test_emoji_value_to_string(): void
    {
        $e = EmojiValue::get('thumbs_up');
        $this->assertSame('{{emoji:thumbs_up}}', (string) $e);
    }

    public function test_emoji_value_to_json(): void
    {
        $e = EmojiValue::get('rocket');
        $this->assertSame('{{emoji:rocket}}', $e->toJson());
    }
}
