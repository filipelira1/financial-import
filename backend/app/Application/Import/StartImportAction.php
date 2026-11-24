<?php

namespace App\Application\Import;

use App\Application\Import\Exceptions\DuplicateImport;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Store an uploaded file and queue its processing. Returns immediately.
 */
final class StartImportAction
{
    /**
     * @throws DuplicateImport when the user already uploaded the same file
     */
    public function execute(User $user, UploadedFile $file): Import
    {
        $hash = hash_file('sha256', $file->getRealPath());

        $this->guardAgainstDuplicate($user, $hash);

        $disk = config('imports.disk');
        $path = $file->storeAs("imports/{$user->id}", Str::uuid7().'.csv', ['disk' => $disk]);

        try {
            $import = $user->imports()->create([
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'stored_path' => $path,
                'file_hash' => $hash,
                'file_size' => $file->getSize(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two identical uploads raced past the check above; the unique index decided.
            Storage::disk($disk)->delete($path);
            $this->guardAgainstDuplicate($user, $hash);

            throw new \LogicException('Unreachable: the unique index reported a duplicate that does not exist.');
        }

        ProcessImportJob::dispatch($import->id);

        return $import;
    }

    private function guardAgainstDuplicate(User $user, string $hash): void
    {
        $existing = $user->imports()->where('file_hash', $hash)->first();

        if ($existing !== null) {
            throw new DuplicateImport($existing);
        }
    }
}
