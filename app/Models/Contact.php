<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'tbl_contacts';

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'organization',
        'title',
        'email',
        'phone',
        'mobile_phone',
        'notes',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function locationContacts()
    {
        return $this->hasMany(LocationContact::class, 'contact_id');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'tbl_location_contacts', 'contact_id', 'location_id')
            ->withPivot([
                'id',
                'account_id',
                'contact_role',
                'is_primary',
                'notes',
            ]);
    }

    public function getDisplayNameAttribute(): string
    {
        // Contacts need a stable human label even when only partial details exist.
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        if ($this->organization) {
            return $this->organization;
        }

        if ($this->email) {
            return $this->email;
        }

        if ($this->phone) {
            return $this->phone;
        }

        return 'Contact #'.$this->id;
    }
}
