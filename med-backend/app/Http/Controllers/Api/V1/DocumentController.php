<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignDocumentsRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
        $this->authorizeResource(Document::class, 'document');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $documents = Document::query()
            ->with(['uploader', 'signer', 'patient.user'])
            // Il paziente vede solo i propri documenti
            ->when($user->isPatient(), fn ($q) => $q->where('patient_id', $user->patient?->id))
            ->when($user->isDoctor(), fn ($q) => $q->whereIn('category', ['referto', 'consenso']))
            ->when($request->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->category($request->query('category'))
            ->status($request->query('status'))
            ->when($request->query('q'), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->latest()
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = $this->documents->store($request->file('file'), $request->validated(), $request->user());

        return response()->json([
            'message' => 'Documento caricato.',
            'data'    => new DocumentResource($document->load('uploader')),
        ], 201);
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load(['uploader', 'signer', 'patient.user']));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->documents->delete($document);

        return response()->json(['message' => 'Documento eliminato.']);
    }

    /** Download autorizzato: il path fisico non viene mai esposto. */
    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk(DocumentService::DISK)->exists($document->file_path), 404, 'File non disponibile.');

        return Storage::disk(DocumentService::DISK)->download($document->file_path, $document->original_name);
    }

    /** Firma singola. */
    public function sign(Request $request, Document $document): JsonResponse
    {
        $this->authorize('sign', $document);

        $request->validate(['signature_type' => ['nullable', 'in:FEA,FEQ']]);

        $document = $this->documents->sign($document, $request->user(), $request->input('signature_type', 'FEQ'));

        return response()->json([
            'message' => 'Documento firmato.',
            'data'    => new DocumentResource($document),
        ]);
    }

    /** Firma massiva: il flusso reale dell'amministratore con decine di pratiche. */
    public function signBulk(SignDocumentsRequest $request): JsonResponse
    {
        $count = $this->documents->signMany(
            $request->input('ids'),
            $request->user(),
            $request->input('signature_type', 'FEQ')
        );

        return response()->json([
            'message' => "{$count} documenti firmati.",
            'signed'  => $count,
        ]);
    }

    /** Invio in conservazione sostitutiva a norma. */
    public function archive(Document $document): JsonResponse
    {
        $this->authorize('sign', $document);

        return response()->json([
            'message' => 'Documento inviato in conservazione.',
            'data'    => new DocumentResource($this->documents->sendToArchive($document)),
        ]);
    }

    /** Contatori per i filtri rapidi della vista Documenti. */
    public function counters(): JsonResponse
    {
        // Contatori aggregati sull'intero archivio: solo amministrazione
        abort_unless(request()->user()->isAdmin(), 403, 'Non hai i permessi per questa operazione.');

        return response()->json([
            'data' => [
                'da_firmare'       => Document::where('status', Document::STATUS_DA_FIRMARE)->count(),
                'firmati'          => Document::where('status', Document::STATUS_FIRMATO)->count(),
                'in_conservazione' => Document::where('status', Document::STATUS_CONSERVAZIONE)->count(),
                'per_categoria'    => Document::selectRaw('category, COUNT(*) as totale')
                                        ->groupBy('category')->pluck('totale', 'category'),
            ],
        ]);
    }
}
