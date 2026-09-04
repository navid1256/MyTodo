<?php

declare(strict_types=1);

namespace App\Localization;

final class Translator
{
    private const FALLBACK_LOCALE = 'en';

    private readonly string $locale;

    /** @var array<string, string> */
    private readonly array $translations;

    public function __construct(string $effectiveLanguage, string $languageDirectory)
    {
        $this->locale = $effectiveLanguage === 'persian' ? 'fa' : self::FALLBACK_LOCALE;

        $fallbackTranslations = $this->loadTranslations($languageDirectory, self::FALLBACK_LOCALE);
        $localeTranslations = $this->locale === self::FALLBACK_LOCALE
            ? []
            : $this->loadTranslations($languageDirectory, $this->locale);

        $this->translations = array_replace($fallbackTranslations, $localeTranslations);
    }

    /**
     * @param array<string, scalar|null> $replacements
     */
    public function translate(string $key, array $replacements = []): string
    {
        $translation = $this->translations[$key] ?? $key;
        $tokens = [];

        foreach ($replacements as $name => $value) {
            $tokens['{' . $name . '}'] = (string) $value;
        }

        return strtr($translation, $tokens);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function direction(): string
    {
        return $this->locale === 'fa' ? 'rtl' : 'ltr';
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->translations;
    }

    /**
     * @return array<string, string>
     */
    private function loadTranslations(string $languageDirectory, string $locale): array
    {
        $path = rtrim($languageDirectory, '/\\') . DIRECTORY_SEPARATOR . $locale . '.php';

        if (!is_file($path)) {
            return [];
        }

        $translations = require $path;

        if (!is_array($translations)) {
            return [];
        }

        return array_filter(
            $translations,
            static fn(mixed $translation, mixed $key): bool => is_string($key) && is_string($translation),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
