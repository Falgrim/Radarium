<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BuilderSpecialityMatcher;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class BuilderSpecialityMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'author_speciality_profile.broad_post_threshold' => 4,
            'author_speciality_profile.broad_post_combined_min' => 0.25,
            'author_speciality_profile.ai_fuzzy_percent_min' => 90.0,
            'author_speciality_profile.ai_fuzzy_score_factor' => 0.5,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function sampleDictionary(): Collection
    {
        $make = static fn (
            int $id,
            ?int $parentId,
            string $title,
            string $short,
            array $kw,
            array $weak = [],
        ): object => (object) [
            'id' => $id,
            'parent_id' => $parentId,
            'title' => $title,
            'short_name' => $short,
            'group_title' => $short,
            'key_words' => $kw,
            'weak_key_words' => $weak,
            'negative_key_words' => [],
        ];

        return collect([
            $make(1, null, 'Монолитные работы / бетонные работы', 'Монолитные', ['монолит', 'бетон', 'опалубка', 'армирование']),
            $make(2, 1, 'Устройство фундаментов', 'Фундаменты', ['фундамент', 'ростверк', 'плита под дом']),
            $make(10, null, 'Монтажные работы', 'Монтажные', ['монтажные работы']),
            $make(11, 10, 'Кладочные работы', 'Кладка', ['каменщик', 'газобетон', 'кладка']),
            $make(20, null, 'Кровельные работы', 'Кровля', ['клик-фальц', 'ендова', 'кровля']),
            $make(30, null, 'Аренда техники и оборудования', 'Аренда', ['манипулятор', 'ямобур', 'экскаватор', 'аренда техники']),
            $make(40, null, 'Отделочные работы', 'Отделка', ['отделочные работы'], ['отделка']),
            $make(41, 40, 'Штукатурные работы', 'Штукатурка', ['штукатурка', 'короед']),
            $make(42, 40, 'Малярные работы', 'Малярка', ['малярка', 'обои', 'покраска']),
        ]);
    }

    public function test_masonry_text_matches_leaf_not_parent(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Ищу бригаду каменщиков на кладку газобетона', null);

        $this->assertContains(11, $r['ids']);
        $this->assertNotContains(10, $r['ids']);
    }

    public function test_foundation_leaf_preferred(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Заливаем фундамент, опалубка и армирование под частник', null);

        $this->assertContains(2, $r['ids']);
        // родитель монолита может остаться только если нет leaf evidence по его детям;
        // фундамент — leaf под монолитом, поэтому parent 1 подавляется
        $this->assertNotContains(1, $r['ids']);
    }

    public function test_roofing_click_falz(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Делаем клик-фальц и ендовы, выезжаю по МСК', null);

        $this->assertContains(20, $r['ids']);
    }

    public function test_equipment_rent(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Манипулятор со стрелой и ямобур-вездеход, машинист с опытом', null);

        $this->assertContains(30, $r['ids']);
    }

    public function test_finishing_leaves_not_parent(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Штукатурка короед, обои, покраска', null);

        $this->assertContains(41, $r['ids']);
        $this->assertContains(42, $r['ids']);
        $this->assertNotContains(40, $r['ids']);
    }

    public function test_resolve_respects_max_speciality_cap(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $unlimited = $matcher->resolve('Штукатурка короед, обои, покраска', null);
        $this->assertGreaterThanOrEqual(2, count($unlimited['ids']));

        $max = 1;
        $limited = $matcher->resolve('Штукатурка короед, обои, покраска', null, $max);
        $this->assertLessThanOrEqual($max, count($limited['ids']));
        foreach ($limited['ids'] as $id) {
            $this->assertContains($id, $unlimited['ids']);
        }
    }

    public function test_project_phrase_no_false_speciality(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Нужны проектные работы, согласование и смета', null);

        $this->assertSame([], $r['ids']);
    }

    public function test_ai_list_matches_by_title(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('', ['Кровельные работы']);

        $this->assertContains(20, $r['ids']);
    }

    public function test_ai_list_matches_by_keyword_not_only_title(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('', ['каменщик']);

        $this->assertContains(11, $r['ids']);
    }

    public function test_ai_prefers_leaf_when_title_equals_group(): void
    {
        $make = static fn (
            int $id,
            ?int $parentId,
            string $title,
            array $kw,
        ): object => (object) [
            'id' => $id,
            'parent_id' => $parentId,
            'title' => $title,
            'short_name' => $title,
            'group_title' => $title,
            'key_words' => $kw,
            'weak_key_words' => [],
            'negative_key_words' => [],
        ];
        $dict = collect([
            $make(40, null, 'Отделочные работы', ['отделочные работы']),
            $make(44, 40, 'Отделочные работы', ['отделочные работы', 'комплексная отделка']),
            $make(41, 40, 'Штукатурные работы', ['штукатурка']),
        ]);
        $matcher = new BuilderSpecialityMatcher($dict);
        $r = $matcher->resolve('', ['Отделочные работы']);

        $this->assertContains(44, $r['ids']);
        $this->assertNotContains(40, $r['ids']);
    }

    public function test_collect_evidence_has_combined_scores(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $ev = $matcher->collectEvidence('Штукатурка короед под ключ', null);

        $this->assertArrayHasKey(41, $ev['by_id']);
        $this->assertGreaterThan(0, $ev['by_id'][41]['combined']);
        $this->assertTrue($ev['by_id'][41]['is_leaf']);
    }

    public function test_single_weak_keyword_alone_does_not_match(): void
    {
        $make = static fn (int $id, array $kw, array $weak): object => (object) [
            'id' => $id,
            'parent_id' => null,
            'title' => 'X'.$id,
            'short_name' => 'X'.$id,
            'group_title' => 'X',
            'key_words' => $kw,
            'weak_key_words' => $weak,
            'negative_key_words' => [],
        ];
        $dict = collect([
            $make(1, [], ['объект']),
            $make(2, ['кровля'], []),
        ]);
        $matcher = new BuilderSpecialityMatcher($dict);
        $r = $matcher->resolve('Работаю на объекте в центре', null);

        $this->assertNotContains(1, $r['ids']);
    }
}
