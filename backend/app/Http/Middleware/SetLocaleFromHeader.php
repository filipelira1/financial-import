<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language from Accept-Language: Brazilian Portuguese or English (the default).
 */
final class SetLocaleFromHeader
{
    /** Laravel locale => language tags that select it (in Symfony's format: "pt_BR", not "pt-BR"). */
    private const array SUPPORTED = ['pt_BR' => ['pt_BR', 'pt'], 'en' => ['en']];

    public function handle(Request $request, Closure $next): Response
    {
        // The client's languages, best first. getPreferredLanguage() is not used because it
        // returns the first supported language even when the client asked for none of them.
        foreach ($request->getLanguages() as $language) {
            $locale = $this->localeFor($language);

            if ($locale !== null) {
                app()->setLocale($locale);
                break;
            }
        }

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale() === 'pt_BR' ? 'pt-BR' : 'en');
        $response->setVary(array_merge($response->getVary(), ['Accept-Language']));

        return $response;
    }

    /** "pt_BR" and "pt" pick Portuguese, "en_US" and "en" pick English. */
    private function localeFor(string $language): ?string
    {
        $primary = explode('_', $language)[0];

        foreach (self::SUPPORTED as $locale => $tags) {
            if (in_array($language, $tags, true) || in_array($primary, $tags, true)) {
                return $locale;
            }
        }

        return null;
    }
}
