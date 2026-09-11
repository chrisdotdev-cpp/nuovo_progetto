<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicineRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\MedicineResource;
use App\Models\Medicine;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Anagrafica farmaci e magazzino. */
class MedicineController extends Controller
{
    public function __construct(private readonly InventoryService $inventory)
    {
        $this->authorizeResource(Medicine::class, 'medicine');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $medicines = Medicine::query()
            ->search($request->query('q'))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->when($request->query('filter') === 'low_stock', fn ($q) => $q->lowStock())
            ->when($request->query('filter') === 'expiring', fn ($q) => $q->expiringWithin((int) $request->query('days', 90)))
            ->orderBy($request->query('sort', 'name'), $request->query('direction', 'asc'))
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return MedicineResource::collection($medicines);
    }

    public function store(StoreMedicineRequest $request): JsonResponse
    {
        $medicine = Medicine::create($request->validated());

        return response()->json([
            'message' => 'Farmaco aggiunto.',
            'data'    => new MedicineResource($medicine),
        ], 201);
    }

    public function show(Medicine $medicine): MedicineResource
    {
        return new MedicineResource($medicine);
    }

    public function update(StoreMedicineRequest $request, Medicine $medicine): JsonResponse
    {
        // La giacenza non si modifica da qui: solo tramite movimento tracciato
        $medicine->update($request->safe()->except('stock_quantity'));

        return response()->json([
            'message' => 'Farmaco aggiornato.',
            'data'    => new MedicineResource($medicine->fresh()),
        ]);
    }

    public function destroy(Medicine $medicine): JsonResponse
    {
        $medicine->delete();

        return response()->json(['message' => 'Farmaco rimosso.']);
    }

    /** Carico/scarico di magazzino con movimento tracciato. */
    public function move(StoreStockMovementRequest $request, Medicine $medicine): JsonResponse
    {
        $movement = $this->inventory->move(
            $medicine,
            $request->type,
            (int) $request->quantity,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'message'  => 'Movimento registrato.',
            'movement' => $movement,
            'data'     => new MedicineResource($medicine->fresh()),
        ], 201);
    }

    /** Storico movimenti di un farmaco. */
    public function movements(Medicine $medicine): JsonResponse
    {
        // Lo storico di magazzino e' informazione gestionale, non clinica
        $this->authorize('manageStock', Medicine::class);

        return response()->json([
            'data' => $medicine->movements()->with('user:id,name')->limit(100)->get(),
        ]);
    }

    /** Riepilogo per la dashboard farmacia. */
    public function summary(): JsonResponse
    {
        $this->authorize('viewAny', Medicine::class);

        return response()->json(['data' => $this->inventory->summary()]);
    }
}
