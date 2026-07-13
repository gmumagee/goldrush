<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_REPAIR = 'repair';
    public const STATUS_RETIRED = 'retired';

    protected $table = 'tbl_machines';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'location_id',
        'type',
        'serial_number',
        'model',
        'status',
        'installed_on',
    ];

    protected $casts = [
        'installed_on' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function bins()
    {
        return $this->hasMany(Bin::class, 'machine_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'location_id', 'location_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'machine_id');
    }
}
