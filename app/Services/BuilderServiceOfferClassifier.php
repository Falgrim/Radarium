<?php

declare(strict_types=1);

namespace App\Services;

final class BuilderServiceOfferClassifier
{
    public function __construct(
        private readonly BuilderAiPipelineRuntimeConfig $runtimeConfig
    ) {}

    public const TYPE_SERVICE = 'предложение услуги';

    public const TYPE_JUNK = 'мусор';

    /**
     * @return array{type: string, origin: string, source: string, reason: string|null}
     */
    public function classify(ApiAIOllama $aiService, string $postText): array
    {
        $hardRejectReason = $this->hardRejectReason($postText);
        if ($hardRejectReason !== null) {
            return $this->result(self::TYPE_JUNK, '{"type":"мусор"}', 'heuristic', $hardRejectReason);
        }

        $aiService->setGenerationOptions(0.0, 64);
        $aiService->setPromt($this->prompt());
        $aiService->setText(trim($postText));

        $result = $aiService->getDecodedJsonResult();
        $json = $result['json'];

        if (array_keys($json) !== ['type']) {
            return $this->result(self::TYPE_JUNK, $result['origin'], 'llm_invalid_shape', 'pass1_expected_only_type');
        }

        $type = $this->normalizeType($json['type'] ?? null);
        if ($type === null) {
            return $this->result(self::TYPE_JUNK, $result['origin'], 'llm_invalid_type', 'pass1_unknown_type');
        }

        return $this->result($type, $result['origin'], 'llm', null);
    }

    private function hardRejectReason(string $postText): ?string
    {
        $trimmed = trim($postText);
        if ($trimmed === '') {
            return 'empty_text';
        }

        foreach ((new CatalogPublicationBuilderNonServiceSignals)->reasons($trimmed) as $reason) {
            return $reason;
        }

        return null;
    }

    private function normalizeType(mixed $type): ?string
    {
        if (! is_string($type)) {
            return null;
        }

        $normalized = mb_strtolower(trim($type));

        return match ($normalized) {
            self::TYPE_SERVICE => self::TYPE_SERVICE,
            self::TYPE_JUNK => self::TYPE_JUNK,
            default => null,
        };
    }

    private function prompt(): string
    {
        return $this->runtimeConfig->pass1Prompt();
    }

    /**
     * @return array{type: string, origin: string, source: string, reason: string|null}
     */
    private function result(string $type, string $origin, string $source, ?string $reason): array
    {
        return [
            'type' => $type,
            'origin' => $origin,
            'source' => $source,
            'reason' => $reason,
        ];
    }
}
