<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Import\TransactionRowParser;
use App\Infrastructure\Csv\CsvReader;
use App\Infrastructure\Csv\InvalidCsvFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreImportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A CSV file of at most 20 MB. The header is checked right away (see after()),
            // so a wrong file is refused before anything is queued.
            'file' => [
                'required',
                'file',
                'extensions:csv,txt',
                'mimetypes:text/csv,text/plain,application/csv,text/x-csv,application/vnd.ms-excel',
                'max:'.config('imports.max_upload_kb'),
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('file');

                if ($validator->errors()->isNotEmpty() || ! $file instanceof UploadedFile) {
                    return;
                }

                try {
                    $reader = CsvReader::open($file->getRealPath())->requireColumns(TransactionRowParser::COLUMNS);

                    if (! $reader->hasDataRows()) {
                        $validator->errors()->add('file', 'The file has a header but no data rows.');
                    }
                } catch (InvalidCsvFile $e) {
                    $validator->errors()->add('file', $e->getMessage());
                }
            },
        ];
    }
}
