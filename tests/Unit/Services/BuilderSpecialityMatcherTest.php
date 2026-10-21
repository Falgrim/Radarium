<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\BuilderSpecialityDictionaryData;
use App\Services\AuthorSpecialitySelection;
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

    public function test_ai_list_does_not_assign_without_text_hit(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $empty = $matcher->resolve('', ['Кровельные работы', 'каменщик']);
        $this->assertSame([], $empty['ids']);

        $withText = $matcher->resolve('Делаем кровельные работы, нужен каменщик', ['Кровельные работы', 'каменщик']);
        $this->assertContains(20, $withText['ids']);
        $this->assertContains(11, $withText['ids']);
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
        $r = $matcher->resolve('Нужны отделочные работы под ключ', ['Отделочные работы']);

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

    public function test_hr_outsourcing_post_does_not_take_unrelated_construction_specialities(): void
    {
        $dict = $this->realDictionary();
        $matcher = new BuilderSpecialityMatcher($dict);
        $staffing = $this->idBySlug($dict, 'sp_staffing');
        $hrGroup = $this->idBySlug($dict, 'grp_hr');
        $monolith = $this->idBySlug($dict, 'sp_monolith');
        $foundation = $this->idBySlug($dict, 'sp_found_pile');
        $screed = $this->idBySlug($dict, 'sp_screed');

        $r = $matcher->resolve($this->hrOutsourcingPost(), [
            'Монолитные работы \\ бетонные работы',
            'Монолитные работы / бетонные работы',
            'Фундаменты, свайные фундаменты',
            'Подбор персонала, кадры, сертификация',
        ]);

        $this->assertSame([$staffing], $r['ids']);
        $this->assertNotContains($hrGroup, $r['ids']);
        $this->assertNotContains($monolith, $r['ids']);
        $this->assertNotContains($foundation, $r['ids']);
        $this->assertNotContains($screed, $r['ids']);

        $evidence = $matcher->collectEvidence($this->hrOutsourcingPost(), [
            'Монолитные работы / бетонные работы',
            'Фундаменты, свайные фундаменты',
        ]);
        $agg = [];
        foreach ($evidence['by_id'] as $id => $row) {
            $agg[(int) $id] = [
                'score' => (float) $row['combined'],
                'share' => 1.0,
                'support' => 1,
            ];
        }
        $selected = AuthorSpecialitySelection::selectTop($agg, 3, 1);

        $this->assertSame([$staffing], $selected);
    }

    public function test_post_without_dictionary_hits_stays_empty_even_if_ai_names_specialities(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->realDictionary());
        $r = $matcher->resolve(
            'Продам iPhone, самовывоз сегодня вечером, торг уместен',
            [
                'Монолитные работы / бетонные работы',
                'Фундаменты, свайные фундаменты',
                'Кровельные работы',
            ]
        );

        $this->assertSame([], $r['ids']);
    }

    public function test_short_stem_does_not_match_unrelated_word(): void
    {
        $dict = $this->realDictionary();
        $matcher = new BuilderSpecialityMatcher($dict);
        $screed = $this->idBySlug($dict, 'sp_screed');

        $r = $matcher->resolve('Работаем по полному авансированию, гарантия результата', null);

        $this->assertNotContains($screed, $r['ids']);
    }

    public function test_monolith_and_foundation_still_match_real_construction_text(): void
    {
        $dict = $this->realDictionary();
        $matcher = new BuilderSpecialityMatcher($dict);
        $r = $matcher->resolve('Выполним монолитные работы и зальём фундамент под дом', null);

        $this->assertContains($this->idBySlug($dict, 'sp_monolith'), $r['ids']);
        $this->assertContains($this->idBySlug($dict, 'sp_found_pile'), $r['ids']);
    }

    private function hrOutsourcingPost(): string
    {
        return 'Услуги HR на аутсорсе! Закрою любую вакансию за 1000 рублей! '
            .'Приветствую! Меня зовут Степан, и я помогу вашему бизнесу найти идеальных сотрудников. '
            .'Больше никакого кадрового голода! Что я предлагаю: - Подбор персонала под любые задачи и требования. '
            .'- Работа по всему миру и любым регионам РФ. - Официальное сотрудничество с физ. и юр. лицами. '
            .'- Стоимость — всего 1000 рублей за человека! Условия работы: Работаем по полному авансированию. '
            .'Даём 100% гарантию результата — вакансия точно будет закрыта целевым кандидатом. '
            .'Связаться со мной: +79069343657 (Степан)';
    }

    /**
     * @return Collection<int, object>
     */
    private function realDictionary(): Collection
    {
        $rows = BuilderSpecialityDictionaryData::rows();
        $idBySlug = [];
        $i = 1;
        foreach ($rows as $row) {
            $idBySlug[(string) $row['slug']] = $i++;
        }

        $items = [];
        foreach ($rows as $row) {
            $parent = $row['parent_slug'] ?? null;
            $items[] = (object) [
                'id' => $idBySlug[(string) $row['slug']],
                'parent_id' => is_string($parent) && isset($idBySlug[$parent]) ? $idBySlug[$parent] : null,
                'title' => $row['title'],
                'short_name' => $row['short_name'],
                'group_title' => $row['group_title'] ?? '',
                'key_words' => $row['key_words'] ?? [],
                'weak_key_words' => $row['weak_key_words'] ?? [],
                'negative_key_words' => $row['negative_key_words'] ?? [],
                'slug' => $row['slug'],
            ];
        }

        return collect($items);
    }

    private function idBySlug(Collection $dict, string $slug): int
    {
        $row = $dict->firstWhere('slug', $slug);
        $this->assertNotNull($row, 'В справочнике нет slug '.$slug);

        return (int) $row->id;
    }
}
