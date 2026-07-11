<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataDictionary extends Model
{
    protected $table = 'tbl_data_dictionary';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'value',
    ];
}
