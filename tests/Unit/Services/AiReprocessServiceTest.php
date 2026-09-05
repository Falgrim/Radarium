<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Services\AiReprocessService;
use Tests\TestCase;

final class AiReprocessServiceTest extends TestCase
{
    private AiReprocessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiReprocessService();
    }

    public function test_default_statuses_cover_unprocessed_posts(): void
    {
        $this->assertSame(
            [ApiChannelPostStatusEnum::Error, ApiChannelPostStatusEnum::Empty],
            AiReprocessService::UNPROCESSED_STATUSES
        );
    }

    public function test_empty_and_unprocessed_fall_back_to_default_statuses(): void
    {
        $this->assertSame(AiReprocessService::UNPROCESSED_STATUSES, $this->service->parseStatuses(''));
        $this->assertSame(AiReprocessService::UNPROCESSED_STATUSES, $this->service->parseStatuses('unprocessed'));
    }

    public function test_all_includes_dont_match_and_complete(): void
    {
        $statuses = $this->service->parseStatuses('all');

        $this->assertContains(ApiChannelPostStatusEnum::DontMatch, $statuses);
        $this->assertContains(ApiChannelPostStatusEnum::Complete, $statuses);
    }

    public function test_explicit_list_is_parsed_and_deduplicated(): void
    {
        $this->assertSame(
            [ApiChannelPostStatusEnum::Error, ApiChannelPostStatusEnum::DontMatch],
            $this->service->parseStatuses('Error, dontmatch, error')
        );
    }

    public function test_unknown_status_returns_null(): void
    {
        $this->assertNull($this->service->parseStatuses('error,wrong'));
    }
}
