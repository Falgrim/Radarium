<?php

namespace Tests\Unit\Support;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Support\CatalogLastMessage;
use Tests\TestCase;

class CatalogLastMessageTest extends TestCase
{
    public function test_specialist_sort_key_takes_date_from_active_specialist_cards(): void
    {
        $query = CatalogLastMessage::specialistSortKey();
        $sql = $this->unquote($query->toSql());

        $this->assertStringContainsString('MAX(api_channel_posts.post_date)', $sql);
        $this->assertStringContainsString('api_channel_posts.api_post_user_id = api_post_users.id', $sql);
        $this->assertStringContainsString('from specialists', $sql);
        $this->assertStringContainsString('specialists.api_post_user_id = api_channel_posts.api_post_user_id', $sql);
        $this->assertStringContainsString('specialists.deleted_at is null', $sql);

        $this->assertContains(ApiChannelPostStatusEnum::Complete->value, $query->getBindings());
        $this->assertContains(ApiPostAiStatusEnum::Active->value, $query->getBindings());
    }

    public function test_builder_sort_key_takes_date_from_active_builder_cards(): void
    {
        $query = CatalogLastMessage::builderSortKey();
        $sql = $this->unquote($query->toSql());

        $this->assertStringContainsString('MAX(api_channel_posts.post_date)', $sql);
        $this->assertStringContainsString('api_channel_posts.api_post_user_id = api_post_users.id', $sql);
        $this->assertStringContainsString('from builders', $sql);
        $this->assertStringContainsString('builders.api_post_user_id = api_channel_posts.api_post_user_id', $sql);
        $this->assertStringContainsString('builders.deleted_at is null', $sql);

        $this->assertContains(ApiChannelPostStatusEnum::Complete->value, $query->getBindings());
        $this->assertContains(ApiPostAiStatusEnum::Active->value, $query->getBindings());
    }

    public function test_author_row_reuses_preloaded_last_message(): void
    {
        $post = new ApiChannelPost;
        $author = new ApiPostUser;

        $author->setRelation(CatalogLastMessage::SPECIALIST_RELATION, $post);
        $this->assertSame($post, $author->lastSpecialistPost());

        $author->setRelation(CatalogLastMessage::BUILDER_RELATION, null);
        $this->assertNull($author->lastBuilderPost());
    }

    /**
     * Экранирование идентификаторов зависит от драйвера, а проверяем мы структуру запроса.
     */
    private function unquote(string $sql): string
    {
        return str_replace(['`', '"'], '', $sql);
    }
}
