<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait FileUploadTrait
{
  /**
   * Upload file ke storage dengan nama unik.
   * * @param UploadedFile $file
   * @param string $folder (e.g., 'venues', 'payments')
   * @param string|null $oldFile (path file lama untuk dihapus)
   * @return string Path file yang berhasil diupload
   */
  public function uploadFile(UploadedFile $file, string $folder, ?string $oldFile = null): string
  {
    // 1. Hapus file lama jika ada (untuk update data)
    if ($oldFile) {
      $this->deleteFile($oldFile);
    }

    // 2. Buat nama file unik: timestamp_slug-nama-asli.ext
    $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();

    // 3. Simpan ke disk 'public' (agar bisa diakses via asset())
    return $file->storeAs($folder, $filename, 'public');
  }

  /**
   * Hapus file dari storage.
   */
  public function deleteFile(?string $path): void
  {
    if ($path && Storage::disk('public')->exists($path)) {
      Storage::disk('public')->delete($path);
    }
  }

  /**
   * Upload banyak file sekaligus (untuk Gallery Lapangan/Venue)
   */
  public function uploadMultipleFiles(array $files, string $folder): array
  {
    $paths = [];
    foreach ($files as $file) {
      if ($file instanceof UploadedFile) {
        $paths[] = $this->uploadFile($file, $folder);
      }
    }
    return $paths;
  }
}
