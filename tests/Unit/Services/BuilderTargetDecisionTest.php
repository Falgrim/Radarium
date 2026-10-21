<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BuilderTargetDecision;
use PHPUnit\Framework\TestCase;

final class BuilderTargetDecisionTest extends TestCase
{
    public function test_service_offer_is_target(): void
    {
        $decision = BuilderTargetDecision::fromModelType(
            'Предложение услуги',
            'Бригада выполнит штукатурку и стяжку, свободны со следующей недели.'
        );

        $this->assertTrue($decision['is_target']);
        $this->assertFalse($decision['overridden_to_vacancy']);
        $this->assertSame('предложение услуги', $decision['ai_type']);
    }

    public function test_resume_alias_is_target(): void
    {
        $decision = BuilderTargetDecision::fromModelType(
            'резюме',
            'Мастер отделочных работ, выполню ремонт квартир.'
        );

        $this->assertTrue($decision['is_target']);
    }

    public function test_junk_and_vacancy_are_not_targets(): void
    {
        $junk = BuilderTargetDecision::fromModelType('мусор', 'Продам диван.');
        $vacancy = BuilderTargetDecision::fromModelType('вакансия', 'Ищем плиточника в штат.');

        $this->assertFalse($junk['is_target']);
        $this->assertFalse($vacancy['is_target']);
        $this->assertFalse($vacancy['overridden_to_vacancy']);
    }

    public function test_hiring_heuristic_removes_service_offer_from_targets(): void
    {
        $decision = BuilderTargetDecision::fromModelType(
            'предложение услуги',
            'Нужны люди на смену. Возраст и паспорт. Пишите в лс.'
        );

        $this->assertFalse($decision['is_target']);
        $this->assertTrue($decision['overridden_to_vacancy']);
        $this->assertSame('вакансия', $decision['ai_type']);
        $this->assertSame('предложение услуги', $decision['type_in']);
    }
}
