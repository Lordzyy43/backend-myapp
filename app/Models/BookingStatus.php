<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingStatus extends Model
{
    use HasFactory;

    protected $table = 'booking_status';

    protected $fillable = [
        'status_name',
    ];

    public $timestamps = false;

    /**
     * RELATION
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'status_id');
    }

    /**
     * 🔥 STATIC HELPER (BEST PRACTICE)
     */

    public static function pending()
    {
        return self::where('status_name', 'pending')->value('id');
    }

    public static function confirmed()
    {
        return self::where('status_name', 'confirmed')->value('id');
    }

    public static function cancelled()
    {
        return self::where('status_name', 'cancelled')->value('id');
    }

    public static function expired()
    {
        return self::where('status_name', 'expired')->value('id');
    }

    public static function finished()
    {
        return self::where('status_name', 'finished')->value('id');
    }
}
