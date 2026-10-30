<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ExpenseProofService
{
    public const MAX_PROOFS = 5;

    /**
     * Simpan foto baru dan hapus foto yang diminta. Total foto dibatasi MAX_PROOFS.
     *
     * @param  Model&object{proofs: mixed}  $owner  model dengan trait HasExpenseProofs
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, int|string>  $removeIds
     */
    public function sync(Model $owner, string $dir, array $files = [], array $removeIds = []): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        if ($removeIds) {
            foreach ($owner->proofs()->whereIn('id', $removeIds)->get() as $proof) {
                $disk->delete($proof->photo_url);
                $proof->delete();
            }
        }

        $room = max(0, self::MAX_PROOFS - $owner->proofs()->count());
        foreach (array_slice($files, 0, $room) as $file) {
            $owner->proofs()->create(['photo_url' => $disk->putFile($dir, $file)]);
        }
    }

    /**
     * Hapus semua foto (file + baris) milik satu pengeluaran.
     *
     * @param  Model&object{proofs: mixed}  $owner
     */
    public function purge(Model $owner): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        foreach ($owner->proofs()->get() as $proof) {
            $disk->delete($proof->photo_url);
            $proof->delete();
        }
    }
}
