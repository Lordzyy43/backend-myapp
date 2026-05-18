<?php

namespace App\Services;

use App\Models\Court;
use App\Traits\FileUploadTrait;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourtService
{
    use FileUploadTrait;

    /**
     * Create Court dengan slug unik & Handle Images
     */
    public function createCourt(array $data): Court
    {
        return DB::transaction(function () use ($data) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $data['venue_id']);

            $court = Court::create([
                'venue_id'       => $data['venue_id'],
                'sport_id'       => $data['sport_id'],
                'name'           => $data['name'],
                'price_per_hour' => $data['price_per_hour'],
                'status'         => $data['status'] ?? 'active',
                'slug'           => $data['slug'],
            ]);

            // Handle Gallery Images jika ada
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $image) {
                    $path = $this->uploadFile($image, 'courts');
                    $court->images()->create([
                        'image_url' => $path // Sesuai model CourtImage kamu
                    ]);
                }
            }

            Log::info("Lapangan baru dibuat: {$court->name} (ID: {$court->id})");
            return $court;
        });
    }

    /**
     * Update Court
     */
    public function updateCourt(Court $court, array $data): Court
    {
        return DB::transaction(function () use ($court, $data) {
            if (isset($data['name']) && $data['name'] !== $court->name) {
                $venueId = $data['venue_id'] ?? $court->venue_id;
                $data['slug'] = $this->generateUniqueSlug($data['name'], $venueId, $court->id);
            }

            $court->update($data);
            Log::info("Lapangan ID: {$court->id} diperbarui.");
            return $court;
        });
    }

    /**
     * Delete Court (Soft Delete + Clean Images)
     */
    public function deleteCourt(Court $court): bool
    {
        return DB::transaction(function () use ($court) {
            // Karena Court pakai SoftDeletes, kita tidak hapus file fisik 
            // kecuali kamu ingin benar-benar menghapusnya (forceDelete).
            // Tapi untuk amannya, kita ikuti alur delete standar:
            return $court->delete();
        });
    }

    /**
     * PRIVATE HELPER: Slug unik per Venue
     */
    private function generateUniqueSlug(string $name, int $venueId, int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (Court::where('slug', $slug)
            ->where('venue_id', $venueId)
            ->where('id', '!=', $ignoreId)
            ->exists()
        ) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }
}