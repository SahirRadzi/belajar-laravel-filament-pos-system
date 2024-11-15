<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'gender',
        'birthday',
        'total_price',
        'note',
        'payment_method_id',
        'paid_amount',
        'change_amount',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function orderProduct(): HasMany 
    {
        return $this->hasMany(OrderProduct::class);
    }
}
