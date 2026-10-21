<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Профиль специализаций автора (builders)
    |--------------------------------------------------------------------------
    |
    | enabled=false — прежняя логика AuthorCatalogSpecialitiesSync (склейка текстов).
    | shadow=true — пишем author_speciality_scores, но не трогаем builder_specialities.
    |
    */
    'enabled' => (bool) env('AUTHOR_SPECIALITY_PROFILE_ENABLED', true),
    'shadow' => (bool) env('AUTHOR_SPECIALITY_PROFILE_SHADOW', false),

    /** Версия матчера; при смене словаря/логики — увеличить и пересчитать evidence. */
    'matcher_version' => (string) env('AUTHOR_SPECIALITY_MATCHER_VERSION', 'v4'),

    'max_specialities' => (int) env('AUTHOR_SPECIALITY_MAX', 3),

    /** Полупериод экспоненциального спада веса поста (месяцы). */
    'recency_half_life_months' => (float) env('AUTHOR_SPECIALITY_RECENCY_HALF_LIFE_MONTHS', 12),
    'recency_min_weight' => (float) env('AUTHOR_SPECIALITY_RECENCY_MIN', 0.3),

    /** Минимальный combined на посте, чтобы считать support. */
    'support_combined_min' => 0.3,

    /** Минимальный суммарный score для основной специализации. */
    'primary_score_min' => 0.20,

    /**
     * Абсолютный пол: если пороги не дали отбора, но есть кандидат с score ≥ floor
     * и хотя бы 1 support — взять топ-1 (спасает явные кейсы ~0.20–0.25).
     */
    'primary_score_floor' => 0.15,

    /** IDF слабого ключа: одного hit достаточно для text_score (иначе нужно 2+ weak). */
    'weak_single_idf_min' => 1.2,

    /** Доля от суммы для вторичной специализации. */
    'secondary_share_min' => 0.20,

    /** Вторичная не слабее этой доли от топ-1. */
    'secondary_vs_top_ratio' => 0.35,

    /** Минимум разных постов с evidence для вторичной (если у автора > 2 постов). */
    'secondary_support_min' => 2,

    /** Для авторов с 1–2 постами: порог combined на лучшем посте. */
    'few_posts_combined_min' => 0.5,

    /** Если специализаций выше порога больше N — масштабировать вклад поста. */
    'broad_post_threshold' => 4,
    'broad_post_combined_min' => 0.25,

    /** Порог нечёткого совпадения ответа ИИ (similar_text %). */
    'ai_fuzzy_percent_min' => 90.0,
    'ai_fuzzy_score_factor' => 0.5,
];
