@if ($banners !== [])
    <div class="ai-provider-health-banner" role="alert" aria-live="assertive">
        @foreach ($banners as $banner)
            <div class="ai-provider-health-banner__item">
                <div class="ai-provider-health-banner__title">
                    ⚠️ ИИ недоступен — обработка сообщений остановлена
                </div>
                <div class="ai-provider-health-banner__meta">
                    <strong>{{ $banner['provider_label'] }}</strong>
                    · {{ $banner['endpoint'] }}
                    · в очереди builder-постов:
                    <strong>{{ number_format((int) $banner['posts_in_queue'], 0, ',', ' ') }}</strong>
                </div>
                @if (! empty($banner['detail']))
                    <div class="ai-provider-health-banner__detail">{{ $banner['detail'] }}</div>
                @endif
                <div class="ai-provider-health-banner__actions">
                    <a href="{{ $apiAiUrl }}" class="ai-provider-health-banner__link">Открыть «Сервисы ИИ»</a>
                    <span class="ai-provider-health-banner__since">
                        с {{ \Illuminate\Support\Carbon::parse($banner['since'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <style>
        .ai-provider-health-banner {
            position: sticky;
            top: 0;
            z-index: 1200;
            margin: 0 0 1rem;
            border: 3px solid #b91c1c;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 45%, #991b1b 100%);
            color: #fff;
            box-shadow: 0 12px 32px rgba(185, 28, 28, 0.45);
            animation: ai-provider-health-banner-pulse 1.6s ease-in-out infinite;
        }

        .ai-provider-health-banner__item {
            padding: 1rem 1.25rem;
        }

        .ai-provider-health-banner__item + .ai-provider-health-banner__item {
            border-top: 1px solid rgba(255, 255, 255, 0.25);
        }

        .ai-provider-health-banner__title {
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.35;
            letter-spacing: 0.01em;
            text-transform: uppercase;
        }

        .ai-provider-health-banner__meta {
            margin-top: 0.5rem;
            font-size: 1rem;
            line-height: 1.45;
        }

        .ai-provider-health-banner__detail {
            margin-top: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            background: rgba(0, 0, 0, 0.22);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.875rem;
            line-height: 1.4;
            word-break: break-word;
        }

        .ai-provider-health-banner__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            align-items: center;
            margin-top: 0.75rem;
        }

        .ai-provider-health-banner__link {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            background: #fff;
            color: #991b1b !important;
            font-weight: 700;
            text-decoration: none !important;
        }

        .ai-provider-health-banner__link:hover {
            background: #fee2e2;
        }

        .ai-provider-health-banner__since {
            font-size: 0.875rem;
            opacity: 0.92;
        }

        @keyframes ai-provider-health-banner-pulse {
            0%, 100% {
                box-shadow: 0 12px 32px rgba(185, 28, 28, 0.45);
            }
            50% {
                box-shadow: 0 0 0 6px rgba(248, 113, 113, 0.35), 0 16px 36px rgba(185, 28, 28, 0.55);
            }
        }
    </style>
@endif
