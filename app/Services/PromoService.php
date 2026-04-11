<?php

namespace App\Services;

use App\Models\Promo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PromoService
 * Handles promotional code business logic including:
 * - Promo code validation
 * - Usage tracking
 * - Discount calculations
 */
class PromoService
{
  /**
   * Validate and retrieve a promo code
   *
   * @param string $code The promo code to validate
   * @return Promo|null
   */
  public function validatePromoCode(string $code): ?Promo
  {
    $promo = Promo::where('promo_code', strtoupper($code))
      ->where('is_active', true)
      ->where('start_date', '<=', now())
      ->where('end_date', '>=', now())
      ->first();

    if (!$promo) {
      Log::warning("Invalid promo code attempted: {$code}");
      return null;
    }

    // Check usage limit
    if ($promo->usage_limit > 0 && $promo->used_count >= $promo->usage_limit) {
      Log::info("Promo code exceeded usage limit: {$code}");
      return null;
    }

    return $promo;
  }

  /**
   * Get promo details
   *
   * @param string $code
   * @return array|null
   */
  public function getPromoDetails(string $code): ?array
  {
    $promo = $this->validatePromoCode($code);

    if (!$promo) {
      return null;
    }

    return [
      'id' => $promo->id,
      'code' => $promo->promo_code,
      'description' => $promo->description,
      'discount_type' => $promo->discount_type,
      'discount_value' => $promo->discount_value,
      'max_discount' => $promo->max_discount,
      'remaining_uses' => max(0, $promo->usage_limit - $promo->used_count),
      'expires_at' => $promo->end_date,
    ];
  }

  /**
   * Calculate discount amount for a promo code
   *
   * @param Promo $promo
   * @param float $basePrice
   * @return float
   */
  public function calculateDiscount(Promo $promo, float $basePrice): float
  {
    $discount = 0;

    if ($promo->discount_type === 'percentage') {
      $discount = ($basePrice * $promo->discount_value) / 100;
    } elseif ($promo->discount_type === 'fixed') {
      $discount = $promo->discount_value;
    }

    // Apply maximum discount cap if exists
    if ($promo->max_discount && $discount > $promo->max_discount) {
      $discount = $promo->max_discount;
    }

    return min($discount, $basePrice); // Don't exceed base price
  }

  /**
   * Increment promo usage count atomically
   *
   * @param Promo $promo
   * @return bool
   */
  public function incrementUsage(Promo $promo): bool
  {
    return DB::transaction(function () use ($promo) {
      // Use update with where to ensure atomic operation
      $updated = Promo::where('id', $promo->id)
        ->where(function ($query) {
          $query->where('usage_limit', 0) // Unlimited
            ->orWhere('used_count', '<', DB::raw('usage_limit'));
        })
        ->increment('used_count');

      if ($updated) {
        Log::info("Promo usage incremented", ['code' => $promo->promo_code]);
        return true;
      }

      Log::warning("Failed to increment promo usage", ['code' => $promo->promo_code]);
      return false;
    });
  }

  /**
   * Deactivate expired promos
   *
   * @return int Number of promos deactivated
   */
  public function deactivateExpiredPromos(): int
  {
    return Promo::where('end_date', '<', now())
      ->where('is_active', true)
      ->update(['is_active' => false]);
  }

  /**
   * Get all active promos
   *
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getActivePromos()
  {
    return Promo::where('is_active', true)
      ->where('start_date', '<=', now())
      ->where('end_date', '>=', now())
      ->get();
  }

  /**
   * Get expiring promos (within 7 days)
   *
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getExpiringPromos()
  {
    return Promo::where('is_active', true)
      ->where('end_date', '<=', now()->addDays(7))
      ->where('end_date', '>=', now())
      ->get();
  }

  /**
   * Create a new promo code
   *
   * @param array $data
   * @return Promo
   */
  public function createPromo(array $data): Promo
  {
    return DB::transaction(function () use ($data) {
      $promo = Promo::create([
        'promo_code' => strtoupper($data['promo_code'] ?? ''),
        'description' => $data['description'] ?? '',
        'discount_type' => $data['discount_type'] ?? 'percentage',
        'discount_value' => $data['discount_value'] ?? 0,
        'max_discount' => $data['max_discount'] ?? null,
        'start_date' => $data['start_date'] ?? now(),
        'end_date' => $data['end_date'] ?? now()->addMonth(),
        'usage_limit' => $data['usage_limit'] ?? 0,
        'used_count' => 0,
        'is_active' => $data['is_active'] ?? true,
      ]);

      Log::info("New promo code created", ['code' => $promo->promo_code]);

      return $promo;
    });
  }

  /**
   * Update an existing promo
   *
   * @param Promo $promo
   * @param array $data
   * @return Promo
   */
  public function updatePromo(Promo $promo, array $data): Promo
  {
    return DB::transaction(function () use ($promo, $data) {
      $promo->update(array_filter([
        'description' => $data['description'] ?? null,
        'discount_type' => $data['discount_type'] ?? null,
        'discount_value' => $data['discount_value'] ?? null,
        'max_discount' => $data['max_discount'] ?? null,
        'start_date' => $data['start_date'] ?? null,
        'end_date' => $data['end_date'] ?? null,
        'usage_limit' => $data['usage_limit'] ?? null,
        'is_active' => isset($data['is_active']) ? $data['is_active'] : null,
      ], fn($v) => $v !== null));

      Log::info("Promo code updated", ['code' => $promo->promo_code]);

      return $promo;
    });
  }
}
