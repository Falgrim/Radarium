<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\BuilderTypeEnum;

/**
 * То же решение, что в app:ai_parse:builder после ответа модели:
 * тип «предложение услуги» (и его алиасы) остаётся целевым,
 * эвристика найма переписывает его в «вакансию».
 */
final class BuilderTargetDecision
{
    /**
     * @return array{is_target: bool, ai_type: string, type_in: string, overridden_to_vacancy: bool}
     */
    public static function fromModelType(string $rawType, string $postText): array
    {
        $typeIn = mb_strtolower(trim($rawType));
        $aiType = $typeIn;
        $overridden = false;

        $serviceLikeTypes = [
            'предложение услуги',
            'предложение услуг',
            'предоставление услуги',
            'резюме',
        ];

        if (
            in_array($aiType, $serviceLikeTypes, true)
            && BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy($postText)
        ) {
            $aiType = 'вакансия';
            $overridden = true;
        }

        return [
            'is_target' => BuilderNormalizer::normalizeType($aiType) === BuilderTypeEnum::Service,
            'ai_type' => $aiType,
            'type_in' => $typeIn,
            'overridden_to_vacancy' => $overridden,
        ];
    }
}
