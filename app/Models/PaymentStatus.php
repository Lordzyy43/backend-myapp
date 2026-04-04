<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentStatus extends Model
{
    use HasFactory;

    protected $table = 'payment_status';

    protected $fillable = [
        'status_name',
    ];

    public $timestamps = false;

    /**
     * RELATION
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'payment_status_id');
    }

    /**
     * 🔥 STATIC HELPER
     */

    public static function pending()
    {
        return self::where('status_name', 'pending')->value('id');
    }

    public static function paid()
    {
        return self::where('status_name', 'paid')->value('id');
    }

    public static function cancelled()
    {
        return self::where('status_name', 'cancelled')->value('id');
    }

    public static function failed()
    {
        return self::where('status_name', 'failed')->value('id');
    }

    public static function expired()
    {
        return self::where('status_name', 'expired')->value('id');
    }
}
