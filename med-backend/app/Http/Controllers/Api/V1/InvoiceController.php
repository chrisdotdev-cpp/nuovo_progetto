<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices)
    {
        $this->authorizeResource(Invoice::class, 'invoice');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $invoices = Invoice::query()
            ->with(['patient.user', 'items'])
            ->when($user->isPatient(), fn ($q) => $q->where('patient_id', $user->patient?->id))
            ->when($request->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->status($request->query('status'))
            ->period($request->query('from'), $request->query('to'))
            ->when($request->boolean('unpaid'), fn ($q) => $q->unpaid())
            ->latest('issue_date')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->validated());

        return response()->json([
            'message' => 'Fattura emessa.',
            'data'    => new InvoiceResource($invoice),
        ], 201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['items', 'payments', 'patient.user']));
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'due_date' => ['nullable', 'date'],
            'notes'    => ['nullable', 'string'],
            'status'   => ['sometimes', 'in:bozza,emessa,pagata,parziale,scaduta,stornata'],
        ]);

        $invoice->update($data);

        return response()->json([
            'message' => 'Fattura aggiornata.',
            'data'    => new InvoiceResource($invoice->fresh(['items', 'payments'])),
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return response()->json(['message' => 'Fattura eliminata.']);
    }

    /** Registrazione incasso: lo stato della fattura si aggiorna da solo. */
    public function pay(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('pay', $invoice);

        $payment = $this->invoices->registerPayment($invoice, $request->validated());

        return response()->json([
            'message' => 'Pagamento registrato.',
            'payment' => new PaymentResource($payment),
            'data'    => new InvoiceResource($invoice->fresh(['items', 'payments'])),
        ], 201);
    }

    /** Report finanziario per la vista admin. */
    public function report(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class); // solo amministrazione

        return response()->json([
            'data' => $this->invoices->report($request->query('from'), $request->query('to')),
        ]);
    }
}
