<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimento di magazzino (immutabile: si registra e non si modifica). */
class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = ['medicine_id', 'user_id', 'type', 'quantity', 'stock_after', 'reason'];

    protected function casts(): array
    {
        return [
            'quantity'    => 'integer',
            'stock_after' => 'integer',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
