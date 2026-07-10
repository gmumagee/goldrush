<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'tbl_services';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'machine_id',
        'user_id',
        'service_type',
        'service_date',
        'status',
    ];

    protected $casts = [
        'service_date' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'service_id');
    }
}
