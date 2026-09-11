<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Fattura verso il paziente. */
class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'patient_id',
        'appointment_id',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'paid_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'  => 'date',
            'due_date'    => 'date',
            'subtotal'    => 'decimal:2',
            'tax_rate'    => 'decimal:2',
            'tax_amount'  => 'decimal:2',
            'total'       => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    protected $appends = ['balance'];

    /** Residuo da incassare. */
    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['emessa', 'parziale', 'scaduta']);
    }

    /**
     * Residuo effettivamente ancora da incassare.
     *
     * ATTENZIONE: sommare `total` sulle fatture non pagate e' sbagliato, perche'
     * ignora gli acconti gia' versati. Con una fattura da 100 e un acconto da 40
     * lo scoperto e' 60, non 100: era il motivo per cui il "da incassare" della
     * dashboard admin non si muoveva dopo un pagamento parziale.
     */
    public static function outstanding(?int $patientId = null): float
    {
        // first() e non value(): value() riscrive la select e perde l'espressione
        $riga = static::query()
            ->unpaid()
            ->when($patientId, fn (Builder $q, int $id) => $q->where('patient_id', $id))
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as residuo')
            ->first();

        return round((float) ($riga->residuo ?? 0), 2);
    }

    /** Intervallo di competenza, usato dai report finanziari. */
    public function scopePeriod(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('issue_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('issue_date', '<=', $to);
        }

        return $query;
    }
}
