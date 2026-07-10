<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountUser extends Model
{
    protected $table = 'tbl_account_users';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'user_id',
        'role',
        'status',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
