<?php

namespace App\Http\Localization;

use Illuminate\Contracts\Translation\Translator;

/**
 * Translates the reasons the queue stores in canonical English (see lang/{locale}/rejection_reasons.php).
 * The patterns are loaded once per request and locale.
 */
final class ReasonTranslator
{
    /** @var array<string, array<string, string>> */
    private array $patterns = [];

    public function __construct(private readonly Translator $translator) {}

    public function translate(string $reason): string
    {
        foreach ($this->patternsFor($this->translator->getLocale()) as $pattern => $replacement) {
            if (preg_match($pattern, $reason) === 1) {
                return (string) preg_replace($pattern, $replacement, $reason);
            }
        }

        return $reason;
    }

    /**
     * @param  list<string>  $reasons
     * @return list<string>
     */
    public function translateAll(array $reasons): array
    {
        return array_map($this->translate(...), $reasons);
    }

    /**
     * @return array<string, string>
     */
    private function patternsFor(string $locale): array
    {
        if (! array_key_exists($locale, $this->patterns)) {
            // A locale without the file (English) gets the key back as a string: nothing to translate.
            $loaded = $this->translator->get('rejection_reasons', [], $locale, false);
            $this->patterns[$locale] = is_array($loaded) ? $loaded : [];
        }

        return $this->patterns[$locale];
    }
}
