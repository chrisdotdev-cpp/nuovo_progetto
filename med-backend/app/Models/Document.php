<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Documento archiviato con flusso di firma. */
class Document extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DA_FIRMARE      = 'da_firmare';
    public const STATUS_FIRMATO         = 'firmato';
    public const STATUS_CONSERVAZIONE   = 'in_conservazione';

    protected $fillable = [
        'patient_id',
        'uploaded_by',
        'category',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'status',
        'signed_by',
        'signed_at',
        'signature_type',
        'archived_at',
        'parent_document_id',
        'version',
    ];

    // file_path non deve mai raggiungere il client: si espone solo la rotta di download
    protected $hidden = ['file_path', 'checksum'];

    protected function casts(): array
    {
        return [
            'signed_at'   => 'datetime',
            'archived_at' => 'datetime',
            'size'        => 'integer',
            'version'     => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_document_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Document::class, 'parent_document_id');
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return blank($category) ? $query : $query->where('category', $category);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }
}
