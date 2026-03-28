<?php

namespace App\Enum;

enum ApiAiSourceEnum:string {
    case YandexGTP4 = 'yandexgtp4';
    case OllamaQwen = 'ollama_qwen';

    public function toString(): ?string
    {
        return match ($this) {
            self::YandexGTP4    => 'Яндекс GPT 4',
            self::OllamaQwen   => 'Ollama Qwen 2.5',
        };
    }

    public function convertToString(): string
    {
        return $this->value;
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::YandexGTP4    => 'success',
            self::OllamaQwen   => 'info',
        };
    }

    public static function getList(): array
    {
        $values = collect(self::cases());

        $result = $values->mapWithKeys(fn ($value): array => [
            $value->value => method_exists($value, 'toString') ? $value->toString() : $value->value
        ]);
        $result->prepend('Все провайдеры', '');

        return $result->toArray();
    }
}
