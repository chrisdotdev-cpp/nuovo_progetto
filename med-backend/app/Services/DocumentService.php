<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload, versioning e firma dei documenti.
 * I file vivono sul disco privato: si servono solo tramite rotta autorizzata.
 */
class DocumentService
{
    public const DISK = 'local'; // storage/app/private in Laravel 12

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function store(UploadedFile $file, array $data, User $uploader): Document
    {
        // Cartella per paziente, o "struttura" per i documenti aziendali
        $folder = $data['patient_id'] ?? null
            ? "documenti/pazienti/{$data['patient_id']}"
            : 'documenti/struttura';

        $path = $file->store($folder, self::DISK);

        $document = Document::create([
            'patient_id'         => $data['patient_id'] ?? null,
            'uploaded_by'        => $uploader->id,
            'category'           => $data['category'] ?? 'altro',
            'title'              => $data['title'],
            'description'        => $data['description'] ?? null,
            'file_path'          => $path,
            'original_name'      => $file->getClientOriginalName(),
            'mime_type'          => $file->getClientMimeType(),
            'size'               => $file->getSize(),
            'checksum'           => hash_file('sha256', $file->getRealPath() ?: Storage::disk(self::DISK)->path($path)),
            'status'             => $data['status'] ?? Document::STATUS_DA_FIRMARE,
            'parent_document_id' => $data['parent_document_id'] ?? null,
            'version'            => isset($data['parent_document_id'])
                ? (Document::find($data['parent_document_id'])?->version ?? 0) + 1
                : 1,
        ]);

        if ($document->status === Document::STATUS_DA_FIRMARE) {
            $this->notifications->documentToSign($document);
        }

        return $document;
    }

    /**
     * Applica la firma. In produzione qui si chiama l'API del provider certificato
     * (InfoCert, Aruba, DocuSign): il risultato e' il documento firmato + marca temporale.
     */
    public function sign(Document $document, User $signer, string $signatureType = 'FEQ'): Document
    {
        $document->update([
            'status'         => Document::STATUS_FIRMATO,
            'signed_by'      => $signer->id,
            'signed_at'      => now(),
            'signature_type' => $signatureType,
        ]);

        return $document->fresh(['signer']);
    }

    /** Firma massiva: il flusso reale dell'amministratore che chiude le pratiche del giorno. */
    public function signMany(array $ids, User $signer, string $signatureType = 'FEQ'): int
    {
        $documents = Document::whereIn('id', $ids)
            ->where('status', Document::STATUS_DA_FIRMARE)
            ->get();

        $documents->each(fn (Document $doc) => $this->sign($doc, $signer, $signatureType));

        return $documents->count();
    }

    /** Invio in conservazione sostitutiva a norma. */
    public function sendToArchive(Document $document): Document
    {
        $document->update([
            'status'      => Document::STATUS_CONSERVAZIONE,
            'archived_at' => now(),
        ]);

        return $document->fresh();
    }

    public function delete(Document $document): void
    {
        // Soft delete del record, il file resta per eventuali obblighi di conservazione
        $document->delete();
    }
}
