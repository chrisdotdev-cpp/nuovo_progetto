<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'patient_id'     => $this->patient_id,
            'category'       => $this->category,
            'title'          => $this->title,
            'description'    => $this->description,
            'original_name'  => $this->original_name,
            'mime_type'      => $this->mime_type,
            'size'           => $this->size,
            'size_label'     => $this->formatSize($this->size),
            'status'         => $this->status,
            'signature_type' => $this->signature_type,
            'signed_at'      => $this->signed_at?->toIso8601String(),
            'is_signed'      => $this->isSigned(),
            'version'        => $this->version,
            // Il path reale non esce mai: si espone solo l'endpoint autorizzato
            'download_url'   => url("/api/v1/documents/{$this->id}/download"),
            'uploader'       => new UserResource($this->whenLoaded('uploader')),
            'signer'         => new UserResource($this->whenLoaded('signer')),
            'patient'        => new PatientResource($this->whenLoaded('patient')),
            'created_at'     => $this->created_at?->toIso8601String(),
        ];
    }

    /** Dimensione leggibile: la formattazione sta qui per non ripeterla in ogni vista. */
    private function formatSize(?int $bytes): string
    {
        if (! $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 1).' '.$units[min($i, 3)];
    }
}
