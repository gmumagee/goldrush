<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationContact extends Model
{
    protected $table = 'tbl_location_contacts';

    protected $fillable = [
        'account_id',
        'location_id',
        'contact_id',
        'contact_role',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
