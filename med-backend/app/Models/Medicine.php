<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Farmaco a magazzino. */
class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'active_ingredient',
        'aic_code',
        'form',
        'dosage',
        'manufacturer',
        'price',
        'requires_prescription',
        'stock_quantity',
        'min_stock',
        'batch',
        'expiry_date',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price'                 => 'decimal:2',
            'requires_prescription' => 'boolean',
            'active'                => 'boolean',
            'stock_quantity'        => 'integer',
            'min_stock'             => 'integer',
            'expiry_date'           => 'date',
        ];
    }

    protected $appends = ['stock_status'];

    /**
     * Semaforo di magazzino calcolato lato server:
     * il frontend deve solo colorare il badge, senza duplicare la regola.
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'esaurito';
        }

        if ($this->stock_quantity <= $this->min_stock) {
            return 'in_esaurimento';
        }

        return 'disponibile';
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('active_ingredient', 'like', "%{$term}%")
              ->orWhere('aic_code', 'like', "%{$term}%");
        });
    }

    /** Sotto soglia di riordino. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock');
    }

    /** In scadenza entro N giorni. */
    public function scopeExpiringWithin(Builder $query, int $days = 90): Builder
    {
        return $query->whereNotNull('expiry_date')
                     ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }
}
