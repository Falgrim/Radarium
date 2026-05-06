<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ApiAIOllama;
use App\Services\BuilderAiPipelineRuntimeConfig;
use App\Services\BuilderServiceOfferClassifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class BuilderServiceOfferClassifierTest extends TestCase
{
    private BuilderServiceOfferClassifier $classifier;

    private ApiAIOllama $ollama;

    protected function setUp(): void
    {
        parent::setUp();

        $runtimeConfigMock = $this->createMock(BuilderAiPipelineRuntimeConfig::class);
        $runtimeConfigMock->method('pass1Prompt')
            ->willReturn((string) config('builder_ai_pipeline.pass1_prompt'));

        $this->classifier = new BuilderServiceOfferClassifier($runtimeConfigMock);
        $this->ollama = new ApiAIOllama;
        $this->ollama->setConfig([
            'host' => 'http://localhost:11434',
            'model' => 'test-model',
        ]);
    }

    public function test_hard_rejects_hiring_without_llm_call(): void
    {
        Http::fake();

        $result = $this->classifier->classify(
            $this->ollama,
            'Требуются маляры, бригада 10-15 человек, аванс на 3 день.'
        );

        $this->assertSame(BuilderServiceOfferClassifier::TYPE_JUNK, $result['type']);
        $this->assertSame('heuristic', $result['source']);
        Http::assertNothingSent();
    }

    public function test_accepts_compact_service_offer_json(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"type":"предложение услуги"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->classifier->classify(
            $this->ollama,
            'Мы бригада электриков, выполняем монтаж, ищем объекты.'
        );

        $this->assertSame(BuilderServiceOfferClassifier::TYPE_SERVICE, $result['type']);
        $this->assertSame('llm', $result['source']);
    }

    public function test_rejects_non_compact_model_shape(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"type":"предложение услуги","reason":"лишнее поле"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->classifier->classify(
            $this->ollama,
            'Я мастер, выполняю отделочные работы.'
        );

        $this->assertSame(BuilderServiceOfferClassifier::TYPE_JUNK, $result['type']);
        $this->assertSame('llm_invalid_shape', $result['source']);
    }

    public function test_rejects_unknown_type_from_model(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"type":"вакансия"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->classifier->classify(
            $this->ollama,
            'Опытная бригада, готовы выйти завтра'
        );

        $this->assertSame(BuilderServiceOfferClassifier::TYPE_JUNK, $result['type']);
        $this->assertSame('llm_invalid_type', $result['source']);
        $this->assertSame('pass1_unknown_type', $result['reason']);
    }

    public function test_throws_exception_when_ollama_response_is_invalid_json(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'not a json',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(\Exception::class);

        $this->classifier->classify(
            $this->ollama,
            'Я мастер, выполняю отделочные работы'
        );
    }
}
