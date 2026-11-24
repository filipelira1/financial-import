<?php

namespace App\Infrastructure\Csv;

use Generator;

/**
 * Streaming CSV reader: memory use does not grow with the file size.
 *
 * Tolerates a UTF-8 BOM, CRLF line endings, "," ";" or tab separators, Latin-1
 * (Windows-1252) text and columns in any order. Blank lines are skipped but still
 * counted, so reported line numbers match what a text editor shows.
 */
final class CsvReader
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    private const array DELIMITERS = [',', ';', "\t"];

    /** @var resource */
    private $handle;

    private string $delimiter;

    /** @var list<string> normalized column names, in file order */
    private array $columns;

    /** Byte offset and line number of the first record after the header. */
    private int $dataOffset;

    private int $dataLine;

    private function __construct(string $path)
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidCsvFile('The file could not be opened.');
        }

        $this->handle = $handle;
        $this->readHeader();
    }

    public static function open(string $path): self
    {
        return new self($path);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /** @return list<string> */
    public function columns(): array
    {
        return $this->columns;
    }

    public function delimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * @param  list<string>  $required
     *
     * @throws InvalidCsvFile
     */
    public function requireColumns(array $required): self
    {
        $missing = array_values(array_diff($required, $this->columns));

        if ($missing !== []) {
            throw new InvalidCsvFile(sprintf(
                'Missing required column(s): %s. Expected header: %s.',
                implode(', ', $missing),
                implode(',', $required),
            ));
        }

        return $this;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, string>|null values keyed by column name, or null when the column count differs
     */
    public function combine(array $fields): ?array
    {
        return count($fields) === count($this->columns) ? array_combine($this->columns, $fields) : null;
    }

    public function hasDataRows(): bool
    {
        foreach ($this->rows($this->dataOffset, $this->dataLine, 1) as $row) {
            return true;
        }

        return false;
    }

    /**
     * Read the whole file once and record where every chunk of $size rows starts.
     *
     * @return array{chunks: list<CsvChunk>, total: int}
     */
    public function plan(int $size): array
    {
        $chunks = [];
        $total = 0;
        $chunkOffset = $chunkLine = 0;

        $this->seek($this->dataOffset);
        $line = $this->dataLine;

        while (true) {
            $offset = ftell($this->handle);
            $fields = $this->readRecord();

            if ($fields === null) {
                break;
            }

            if ($fields !== []) {
                if ($total % $size === 0) {
                    [$chunkOffset, $chunkLine] = [$offset, $line];
                }

                $total++;

                if ($total % $size === 0) {
                    $chunks[] = new CsvChunk($chunkOffset, $chunkLine, $size);
                }
            }

            $line += $this->linesSpanned($fields);
        }

        if ($total % $size !== 0) {
            $chunks[] = new CsvChunk($chunkOffset, $chunkLine, $total % $size);
        }

        return ['chunks' => $chunks, 'total' => $total];
    }

    /**
     * @return Generator<int, CsvRow>
     */
    public function rows(int $offset, int $firstLine, int $limit): Generator
    {
        $this->seek($offset);
        $line = $firstLine;
        $read = 0;

        while ($read < $limit && ($fields = $this->readRecord()) !== null) {
            if ($fields !== []) {
                $read++;

                yield new CsvRow($line, $fields);
            }

            $line += $this->linesSpanned($fields);
        }
    }

    /**
     * @return Generator<int, CsvRow>
     */
    public function chunk(CsvChunk $chunk): Generator
    {
        return $this->rows($chunk->offset, $chunk->firstLine, $chunk->rows);
    }

    private function readHeader(): void
    {
        $firstLine = fgets($this->handle);

        if ($firstLine === false || trim($firstLine, self::UTF8_BOM." \t\r\n") === '') {
            throw new InvalidCsvFile('The file is empty.');
        }

        $start = str_starts_with($firstLine, self::UTF8_BOM) ? strlen(self::UTF8_BOM) : 0;
        $this->delimiter = $this->detectDelimiter(substr($firstLine, $start));

        $this->seek($start);
        $header = $this->readRecord() ?? [];

        $this->columns = array_map(fn (string $name) => mb_strtolower(trim($name)), $header);

        if (count($this->columns) !== count(array_unique($this->columns))) {
            throw new InvalidCsvFile('The header has duplicated column names.');
        }

        $this->dataOffset = (int) ftell($this->handle);
        $this->dataLine = 1 + $this->linesSpanned($header);
    }

    private function detectDelimiter(string $headerLine): string
    {
        $counts = array_map(fn (string $delimiter) => substr_count($headerLine, $delimiter), self::DELIMITERS);

        return max($counts) > 0 ? self::DELIMITERS[array_search(max($counts), $counts, true)] : ',';
    }

    /**
     * @return list<string>|null the record's fields, [] for a blank line, null at the end of the file
     */
    private function readRecord(): ?array
    {
        $fields = fgetcsv($this->handle, null, $this->delimiter, '"', '');

        if ($fields === false) {
            return null;
        }

        if ($fields === [null]) {
            return [];
        }

        return array_map($this->toUtf8(...), $fields);
    }

    /**
     * A quoted field may contain line breaks, so one record can span several lines.
     *
     * @param  list<string>  $fields
     */
    private function linesSpanned(array $fields): int
    {
        return 1 + array_sum(array_map(fn (string $field) => substr_count($field, "\n"), $fields));
    }

    private function toUtf8(?string $value): string
    {
        $value ??= '';

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    private function seek(int $offset): void
    {
        fseek($this->handle, $offset);
    }
}
