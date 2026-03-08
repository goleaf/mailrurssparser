<?php

namespace App\Services;

use Illuminate\Support\Str;

trait TranslatableBackedEnum
{
    abstract protected function translationGroup(): string;

    public function translationKey(): string
    {
        return 'enums.'.$this->translationGroup().'.'.$this->value;
    }

    public function label(?string $locale = null): string
    {
        $translationKey = $this->translationKey();
        $label = __($translationKey, [], $locale);

        if ($label === $translationKey) {
            return Str::headline((string) $this->value);
        }

        return $label;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($locale);
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    public static function fromValue(self|string|null $value, ?self $default = null): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return self::tryFrom($value) ?? $default;
    }
}
