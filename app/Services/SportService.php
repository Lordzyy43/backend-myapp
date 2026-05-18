<?php

namespace App\Services;

use App\Models\Sport;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * SportService
 * Mengelola kategori olahraga (Master Data), termasuk:
 * - Manajemen file (icon & image)
 * - Pengaturan urutan tampil (sort_order)
 * - Sinkronisasi slug
 */
class SportService
{
  /**
   * UNTUK SEMUA: List sport aktif (biasanya untuk filter di Mobile/FE)
   * * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getActiveSports()
  {
    return Sport::where('is_active', true)
      ->orderBy('sort_order', 'asc')
      ->orderBy('name', 'asc')
      ->get();
  }

  /**
   * UNTUK ADMIN: Management list dengan pagination
   * * @param int $perPage
   * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
   */
  public function getAllForAdmin(int $perPage = 20)
  {
    return Sport::orderBy('sort_order', 'asc')
      ->latest()
      ->paginate($perPage);
  }

  /**
   * LOGIK STORE: Membuat kategori olahraga baru
   * * @param array $data
   * @return Sport
   */
  public function createSport(array $data): Sport
  {
    return DB::transaction(function () use ($data) {
      // Slug otomatis dari name jika tidak diinput manual
      $data['slug'] = Str::slug($data['slug'] ?? $data['name']);

      // Set default sort order jika kosong
      if (!isset($data['sort_order'])) {
        $data['sort_order'] = Sport::max('sort_order') + 1;
      }

      $sport = Sport::create($data);

      Log::info("Kategori Olahraga baru dibuat: {$sport->name}", ['id' => $sport->id]);

      return $sport;
    });
  }

  /**
   * LOGIK UPDATE: Mengupdate kategori olahraga
   * * @param Sport $sport
   * @param array $data
   * @return Sport
   */
  public function updateSport(Sport $sport, array $data): Sport
  {
    return DB::transaction(function () use ($sport, $data) {
      // Jika nama berubah tapi slug tidak dikirim, update slug-nya
      if (isset($data['name']) && !isset($data['slug'])) {
        $data['slug'] = Str::slug($data['name']);
      }

      // Simpan path lama jika ada penggantian file (untuk cleanup nanti)
      $oldIcon = $sport->icon;
      $oldImage = $sport->image;

      $sport->update($data);

      // Jika ada file baru, hapus file lama dari storage agar tidak menumpuk
      if (isset($data['icon']) && $oldIcon && $data['icon'] !== $oldIcon) {
        Storage::disk('public')->delete($oldIcon);
      }
      if (isset($data['image']) && $oldImage && $data['image'] !== $oldImage) {
        Storage::disk('public')->delete($oldImage);
      }

      Log::info("Kategori Olahraga ID: {$sport->id} berhasil diperbarui");

      return $sport;
    });
  }

  /**
   * LOGIK DELETE: Menghapus data dan membersihkan storage
   * * @param Sport $sport
   * @return bool|null
   */
  public function deleteSport(Sport $sport)
  {
    return DB::transaction(function () use ($sport) {
      // Hapus file fisik
      if ($sport->icon) {
        Storage::disk('public')->delete($sport->icon);
      }
      if ($sport->image) {
        Storage::disk('public')->delete($sport->image);
      }

      Log::info("Kategori Olahraga dihapus: {$sport->name}");

      return $sport->delete();
    });
  }

  /**
   * UPDATE STATUS: Toggle aktif/non-aktif secara cepat
   */
  public function toggleStatus(Sport $sport): bool
  {
    return $sport->update([
      'is_active' => !$sport->is_active
    ]);
  }
}
