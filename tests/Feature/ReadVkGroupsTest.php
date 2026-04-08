<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiAi;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\Configuration;
use App\Services\ReadVkGroups;
use App\Services\VkApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReadVkGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vk.service_token' => 'fake-token',
            'vk.api_version' => '5.199',
            'vk.min_interval_us' => 0,
            'vk.max_wall_pages_per_run' => 5,
            'vk.skip_reposts' => false,
            'vk.download_avatars' => false,
        ]);

        Configuration::query()->create([
            'title' => 'cron_count_posts',
            'name' => 'cron_count_posts',
            'type' => 'string',
            'value' => '50',
            'order' => 1,
            'options' => null,
        ]);
        Configuration::query()->create([
            'title' => 'min_length_post',
            'name' => 'min_length_post',
            'type' => 'string',
            'value' => '5',
            'order' => 2,
            'options' => null,
        ]);
    }

    public function test_screen_name_from_link_strips_query_and_path(): void
    {
        $this->assertSame('stroypiter', VkApiClient::screenNameFromLink('https://vk.com/stroypiter?search_track_code=abc'));
        $this->assertSame('club164811551', VkApiClient::screenNameFromLink('https://vk.com/club164811551'));
    }

    public function test_wall_post_persisted_and_user_resolved(): void
    {
        $now = time();
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($now) {
            $url = $request->url();
            if (str_contains($url, 'method/wall.get')) {
                return Http::response([
                    'response' => [
                        'count' => 1,
                        'items' => [
                            [
                                'id' => 9001,
                                'date' => $now,
                                'text' => 'Нужна бригада на объект, звоните.',
                                'from_id' => 200,
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($url, 'method/users.get')) {
                return Http::response([
                    'response' => [
                        [
                            'id' => 200,
                            'first_name' => 'Иван',
                            'last_name' => 'Тестов',
                            'screen_name' => 'ivan_test',
                            'photo_200' => 'https://vk.com/photo.jpg',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => ['error_code' => 0, 'error_msg' => 'unexpected '.$url]], 200);
        });

        $ai = ApiAi::query()->create([
            'title' => 'Test AI',
            'description' => 'd',
            'api_source' => ApiAiSourceEnum::OllamaQwen->value,
            'options' => [],
            'status' => 1,
            'balance_sum' => 0,
            'date_balance' => null,
        ]);

        $channel = ApiChannel::query()->create([
            'title' => 'VK test wall',
            'link' => 'https://vk.com/public999001',
            'description' => 'test',
            'ai_promt' => null,
            'api_ai_id' => $ai->id,
            'channel_source' => ApiChannelSourceEnum::VK,
            'options' => ['owner_id' => -999001],
            'status' => ApiChannelStatusEnum::Active,
            'is_company' => ApiDataTypeEnum::Builder,
            'region' => null,
            'post_from_date' => now()->subYear()->toDateString(),
        ]);

        $service = app(ReadVkGroups::class);
        $service->read($channel);

        $this->assertDatabaseHas('api_channel_posts', [
            'api_channel_id' => $channel->id,
            'post_id' => 9001,
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue->value,
        ]);

        $post = ApiChannelPost::query()->where('api_channel_id', $channel->id)->firstOrFail();
        $this->assertStringContainsString('бригада', $post->post);
        $this->assertSame(200, $post->user_login_id);
        $this->assertDatabaseHas('api_post_users', [
            'user_id' => 200,
            'username' => 'ivan_test',
        ]);
    }

    public function test_repost_includes_copy_history_text_by_default(): void
    {
        $now = time();
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($now) {
            $url = $request->url();
            if (str_contains($url, 'method/wall.get')) {
                return Http::response([
                    'response' => [
                        'count' => 1,
                        'items' => [
                            [
                                'id' => 9003,
                                'date' => $now,
                                'text' => 'Смотрите вакансию ниже.',
                                'from_id' => 200,
                                'copy_history' => [
                                    [
                                        'id' => 99,
                                        'date' => $now - 10,
                                        'text' => 'Ищу работу каменщиком в Москве, опыт 10 лет подряд.',
                                        'from_id' => 300,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }
            if (str_contains($url, 'method/users.get')) {
                return Http::response([
                    'response' => [
                        [
                            'id' => 200,
                            'first_name' => 'А',
                            'last_name' => 'Б',
                            'screen_name' => 'user200',
                            'photo_200' => '',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => ['error_code' => 0, 'error_msg' => 'unexpected '.$url]], 200);
        });

        $ai = ApiAi::query()->create([
            'title' => 'Test AI',
            'description' => 'd',
            'api_source' => ApiAiSourceEnum::OllamaQwen->value,
            'options' => [],
            'status' => 1,
            'balance_sum' => 0,
            'date_balance' => null,
        ]);

        $channel = ApiChannel::query()->create([
            'title' => 'VK repost merge test',
            'link' => 'https://vk.com/public999003',
            'description' => 'test',
            'ai_promt' => null,
            'api_ai_id' => $ai->id,
            'channel_source' => ApiChannelSourceEnum::VK,
            'options' => ['owner_id' => -999003],
            'status' => ApiChannelStatusEnum::Active,
            'is_company' => ApiDataTypeEnum::Builder,
            'region' => null,
            'post_from_date' => now()->subYear()->toDateString(),
        ]);

        app(ReadVkGroups::class)->read($channel);

        $post = ApiChannelPost::query()->where('api_channel_id', $channel->id)->where('post_id', 9003)->firstOrFail();
        $this->assertStringContainsString('Смотрите вакансию', $post->post);
        $this->assertStringContainsString('каменщиком', $post->post);
        $this->assertStringContainsString('---', $post->post);
    }

    public function test_repost_skipped_when_configured(): void
    {
        config(['vk.skip_reposts' => true]);

        $now = time();
        Http::fake([
            '*' => Http::response([
                'response' => [
                    'count' => 1,
                    'items' => [
                        [
                            'id' => 9002,
                            'date' => $now,
                            'text' => 'Репост вакансии с достаточной длиной текста.',
                            'from_id' => 200,
                            'copy_history' => [['id' => 1, 'owner_id' => -1]],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $ai = ApiAi::query()->create([
            'title' => 'Test AI',
            'description' => 'd',
            'api_source' => ApiAiSourceEnum::OllamaQwen->value,
            'options' => [],
            'status' => 1,
            'balance_sum' => 0,
            'date_balance' => null,
        ]);

        $channel = ApiChannel::query()->create([
            'title' => 'VK repost test',
            'link' => 'https://vk.com/public999002',
            'description' => 'test',
            'ai_promt' => null,
            'api_ai_id' => $ai->id,
            'channel_source' => ApiChannelSourceEnum::VK,
            'options' => ['owner_id' => -999002],
            'status' => ApiChannelStatusEnum::Active,
            'is_company' => ApiDataTypeEnum::Builder,
            'region' => null,
            'post_from_date' => now()->subYear()->toDateString(),
        ]);

        app(ReadVkGroups::class)->read($channel);

        $this->assertDatabaseMissing('api_channel_posts', [
            'api_channel_id' => $channel->id,
            'post_id' => 9002,
        ]);
    }
}
