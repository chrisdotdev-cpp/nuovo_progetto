<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ricetta con le sue righe di farmaci. */
class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'patient_id',
        'doctor_id',
        'appointment_id',
        'status',
        'notes',
        'issued_at',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'   => 'date',
            'valid_until' => 'date',
        ];
    }

    protected $appends = ['is_expired'];

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'attiva')
                     ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()));
    }
}
