<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Operator extends Model
{

    protected $fillable = [
        'operator_id',
        'email',
        'password',
        'csrf_token_key',
        'csrf_token_value',
        'oxy_kratos_session',
        'xsrf_token',
        'monta_session',
    ];
}
