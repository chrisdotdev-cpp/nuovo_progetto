<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Movimentazione del magazzino farmaci.
 * La giacenza si modifica SOLO da qui, sempre con movimento tracciato.
 */
class InventoryService
{
    /** Tipi che sottraggono giacenza. */
    private const NEGATIVE = ['scarico', 'scaduto'];

    public function move(Medicine $medicine, string $type, int $quantity, User $user, ?string $reason = null): StockMovement
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La quantita\' deve essere maggiore di zero.']);
        }

        return DB::transaction(function () use ($medicine, $type, $quantity, $user, $reason) {
            // Lock pessimistico: due scarichi simultanei non possono portare la giacenza sotto zero
            $medicine = Medicine::lockForUpdate()->findOrFail($medicine->id);

            $delta = in_array($type, self::NEGATIVE, true) ? -$quantity : $quantity;

            if ($type === 'rettifica') {
                // La rettifica imposta il valore assoluto della giacenza
                $newStock = $quantity;
            } else {
                $newStock = $medicine->stock_quantity + $delta;
            }

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Giacenza insufficiente: disponibili {$medicine->stock_quantity} unita'.",
                ]);
            }

            $medicine->update(['stock_quantity' => $newStock]);

            return StockMovement::create([
                'medicine_id' => $medicine->id,
                'user_id'     => $user->id,
                'type'        => $type,
                'quantity'    => $quantity,
                'stock_after' => $newStock,
                'reason'      => $reason,
            ]);
        });
    }

    /** Riepilogo per la dashboard farmacia. */
    public function summary(): array
    {
        return [
            'totale_farmaci'  => Medicine::where('active', true)->count(),
            'in_esaurimento'  => Medicine::where('active', true)->lowStock()->count(),
            'in_scadenza_90g' => Medicine::where('active', true)->expiringWithin(90)->count(),
            'valore_stock'    => round((float) Medicine::where('active', true)
                                    ->selectRaw('SUM(price * stock_quantity) as v')->value('v'), 2),
        ];
    }
}
