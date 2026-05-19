<?php

namespace Tests\Unit\Support;

use App\Support\TelegramPostUrl;
use PHPUnit\Framework\TestCase;

class TelegramPostUrlTest extends TestCase
{
    public function test_builds_public_channel_post_url(): void
    {
        $url = TelegramPostUrl::fromChannelLinkAndPostId('https://t.me/example_channel', 42);

        $this->assertSame('https://t.me/example_channel/42', $url);
    }

    public function test_returns_null_for_invite_link(): void
    {
        $url = TelegramPostUrl::fromChannelLinkAndPostId('https://t.me/+AbCdEfGh', 42);

        $this->assertNull($url);
    }

    public function test_returns_null_without_post_id(): void
    {
        $this->assertNull(TelegramPostUrl::fromChannelLinkAndPostId('https://t.me/channel', 0));
    }
}
