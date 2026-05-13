<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BuilderSpecialityMatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class BuilderSpecialityMatcherTest extends TestCase
{
    /**
     * @return Collection<int, object>
     */
    private function sampleDictionary(): Collection
    {
        $make = static fn (int $id, ?int $parentId, string $title, string $short, array $kw): object => (object) [
            'id' => $id,
            'parent_id' => $parentId,
            'title' => $title,
            'short_name' => $short,
            'group_title' => $short,
            'key_words' => $kw,
        ];

        return collect([
            $make(1, null, 'Монолитные работы / бетонные работы', 'Монолитные', ['монолит', 'бетон', 'опалубка', 'армирование']),
            $make(2, 1, 'Устройство фундаментов', 'Фундаменты', ['фундамент', 'ростверк', 'плита под дом']),
            $make(10, null, 'Монтажные работы', 'Монтажные', ['монтажные работы']),
            $make(11, 10, 'Кладочные работы', 'Кладка', ['каменщик', 'газобетон', 'кладка']),
            $make(20, null, 'Кровельные работы', 'Кровля', ['клик-фальц', 'ендова', 'кровля']),
            $make(30, null, 'Аренда техники и оборудования', 'Аренда', ['манипулятор', 'ямобур', 'экскаватор', 'аренда техники']),
            $make(40, null, 'Отделочные работы', 'Отделка', ['отделочные работы', 'отделка']),
            $make(41, 40, 'Штукатурные работы', 'Штукатурка', ['штукатурка', 'короед']),
            $make(42, 40, 'Малярные работы', 'Малярка', ['малярка', 'обои', 'покраска']),
        ]);
    }

    public function test_masonry_text_matches_child_and_parent(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Ищу бригаду каменщиков на кладку газобетона', null);

        $this->assertContains(11, $r['ids']);
        $this->assertContains(10, $r['ids']);
    }

    public function test_foundation_and_monolithic(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Заливаем фундамент, опалубка и армирование под частник', null);

        $this->assertContains(2, $r['ids']);
        $this->assertContains(1, $r['ids']);
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

    public function test_finishing_plaster_paint_parents(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $r = $matcher->resolve('Штукатурка короед, обои, покраска', null);

        $this->assertContains(41, $r['ids']);
        $this->assertContains(42, $r['ids']);
        $this->assertContains(40, $r['ids']);
    }

    public function test_resolve_respects_max_speciality_cap(): void
    {
        $matcher = new BuilderSpecialityMatcher($this->sampleDictionary());
        $unlimited = $matcher->resolve('Штукатурка короед, обои, покраска', null);
        $this->assertGreaterThanOrEqual(3, count($unlimited['ids']));

        $max = 3;
        $limited = $matcher->resolve('Штукатурка короед, обои, покраска', null, $max);
        $this->assertLessThanOrEqual($max, count($limited['ids']));
        $this->assertSame(min($max, count($unlimited['ids'])), count($limited['ids']));
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
}
