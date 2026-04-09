<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promo extends Model
{
    use HasFactory;

    protected $fillable = [
        'promo_code',
        'description',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // 🔥 Check promo valid sekarang
    public function isValid(): bool
    {
        $today = now()->toDateString();

        if (!$this->is_active) return false;
        if ($this->start_date > $today) return false;
        if ($this->end_date < $today) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;

        return true;
    }

    // 🔥 Hitung diskon
    public function calculateDiscount(float $totalAmount): float
    {
        if (!$this->isValid()) return 0;

        $discount = $this->discount_type === 'percentage'
            ? ($totalAmount * $this->discount_value / 100)
            : $this->discount_value;
        return min($discount, $totalAmount); // Diskon tidak boleh melebihi total
    }

    // 🔥 Increment used count setelah berhasil dipakai (jaga limit)
    public function markUsed(): bool
    {
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return $this->increment('used_count');
    }
}
