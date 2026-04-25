<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiRequeueBuilderMissingVisiblePostsTest extends TestCase
{
    public function test_requires_dry_run_or_apply(): void
    {
        $code = Artisan::call('app:ai_parse:requeue-builder-missing-visible-posts');

        $this->assertSame(2, $code);
        $this->assertStringContainsString('ровно один режим', Artisan::output());
    }

    public function test_rejects_both_dry_run_and_apply(): void
    {
        $code = Artisan::call('app:ai_parse:requeue-builder-missing-visible-posts', [
            '--dry-run' => true,
            '--apply' => true,
        ]);

        $this->assertSame(2, $code);
    }
}
