<?php

namespace App\Infrastructure\Csv;

use RuntimeException;

/**
 * The message is a translation template (":name" placeholders). getMessage() is the English text,
 * which is what the queue stores; the HTTP layer translates the template for the request's language.
 */
final class InvalidCsvFile extends RuntimeException
{
    /**
     * @param  array<string, string>  $replace
     */
    public function __construct(public readonly string $template, public readonly array $replace = [])
    {
        parent::__construct(strtr($template, array_combine(
            array_map(fn (string $key) => ':'.$key, array_keys($replace)),
            array_values($replace),
        )));
    }
}
