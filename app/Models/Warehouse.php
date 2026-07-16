<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'tbl_warehouses';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'warehouse_name',
        'address',
        'city',
        'state',
        'zip_code',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'warehouse_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'warehouse_id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class, 'warehouse_id')
            ->orderBy('start_at');
    }

    public function inventoryLedger()
    {
        return $this->hasMany(InventoryLedger::class, 'warehouse_id');
    }
}
