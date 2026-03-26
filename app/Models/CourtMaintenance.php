<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class CourtMaintenance extends Model
{
    use HasFactory;

    protected $table = 'court_maintenance';

    protected $fillable = [
        'court_id',
        'start_date',
        'end_date',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * 🔥 CHECK apakah tanggal kena maintenance
     */
    public function scopeActiveOnDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }
}
