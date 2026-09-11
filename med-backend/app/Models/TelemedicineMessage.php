<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Messaggio di chat all'interno di una sessione di telemedicina. */
class TelemedicineMessage extends Model
{
    use HasFactory;

    protected $fillable = ['telemedicine_session_id', 'user_id', 'body', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TelemedicineSession::class, 'telemedicine_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
