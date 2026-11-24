<?php

namespace App\Domain\Import;

use App\Domain\Finance\TransactionData;
use App\Domain\Finance\TransactionType;
use DateTimeImmutable;

/**
 * Turns one source row into a transaction, or explains every reason it cannot.
 *
 * Rules (see README, "Import rules"):
 *  - date: YYYY-MM-DD and a real calendar date;
 *  - description: required, at most 255 characters;
 *  - amount: a positive integer number of cents (no sign, separators or decimals);
 *  - type: "Receita" or "Despesa" (also "income" / "expense"), case-insensitive.
 */
final class TransactionRowParser
{
    public const array COLUMNS = ['date', 'description', 'amount', 'type'];

    private const int MAX_DESCRIPTION_LENGTH = 255;

    /** @var array<string, TransactionType> */
    private const array TYPE_LABELS = [
        'receita' => TransactionType::Income,
        'despesa' => TransactionType::Expense,
        'income' => TransactionType::Income,
        'expense' => TransactionType::Expense,
    ];

    /**
     * @param  array<string, string|null>  $values  row values keyed by column name
     */
    public function parse(array $values): TransactionData|RowRejection
    {
        $errors = [];

        $date = $this->parseDate(trim((string) ($values['date'] ?? '')), $errors);
        $description = $this->parseDescription(trim((string) ($values['description'] ?? '')), $errors);
        $amount = $this->parseAmount(trim((string) ($values['amount'] ?? '')), $errors);
        $type = $this->parseType(trim((string) ($values['type'] ?? '')), $errors);

        if ($errors !== [] || $date === null || $description === null || $amount === null || $type === null) {
            return new RowRejection($errors);
        }

        return new TransactionData($date, $description, $amount, $type);
    }

    /** @param list<string> $errors */
    private function parseDate(string $value, array &$errors): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        // The round trip rejects overflowing dates such as 2025-02-30 (which PHP would turn into March 2nd).
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $errors[] = sprintf('date: expected a valid YYYY-MM-DD date, got "%s"', $this->excerpt($value));

            return null;
        }

        return $date;
    }

    /** @param list<string> $errors */
    private function parseDescription(string $value, array &$errors): ?string
    {
        if ($value === '') {
            $errors[] = 'description: is required';

            return null;
        }

        if (mb_strlen($value) > self::MAX_DESCRIPTION_LENGTH) {
            $errors[] = sprintf('description: must be at most %d characters', self::MAX_DESCRIPTION_LENGTH);

            return null;
        }

        return $value;
    }

    /** @param list<string> $errors */
    private function parseAmount(string $value, array &$errors): ?int
    {
        // Up to 18 digits always fits a signed 64-bit integer.
        if (preg_match('/^\d{1,18}$/', $value) !== 1 || (int) $value === 0) {
            $errors[] = sprintf('amount: expected a positive integer in cents, got "%s"', $this->excerpt($value));

            return null;
        }

        return (int) $value;
    }

    /** @param list<string> $errors */
    private function parseType(string $value, array &$errors): ?TransactionType
    {
        $type = self::TYPE_LABELS[mb_strtolower($value)] ?? null;

        if ($type === null) {
            $errors[] = sprintf('type: expected "Receita" or "Despesa", got "%s"', $this->excerpt($value));
        }

        return $type;
    }

    private function excerpt(string $value): string
    {
        return mb_strimwidth($value, 0, 40, '...');
    }
}
