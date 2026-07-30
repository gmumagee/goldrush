<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanUpgradeRequest extends Model
{
    protected $table = 'plan_upgrade_requests';

    protected $fillable = [
        'account_id',
        'requested_by_user_id',
        'current_plan_id',
        'requested_plan_id',
        'contact_email',
        'source',
        'machine_count',
        'notes',
    ];

    protected $casts = [
        'machine_count' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function currentPlan()
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function requestedPlan()
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
