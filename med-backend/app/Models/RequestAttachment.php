<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Allegato di una richiesta paziente (esame, foto). */
class RequestAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['patient_request_id', 'file_path', 'original_name', 'mime_type', 'size'];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PatientRequest::class, 'patient_request_id');
    }
}
