<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class LocationDocument extends Model
{
    protected $table = 'tbl_location_documents';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'location_id',
        'document_type',
        'title',
        'description',
        'original_filename',
        'stored_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_size',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'location_id' => 'integer',
        'file_size' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function deleteStoredFile(): void
    {
        if (
            $this->storage_disk
            && $this->storage_path
            && Storage::disk($this->storage_disk)->exists($this->storage_path)
        ) {
            Storage::disk($this->storage_disk)->delete($this->storage_path);
        }
    }
}
