<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingTask extends Model
{
  // WordPress table name
    protected $table = 'fc_shipping_method_restrictions';

    protected $fillable = [
        'method_id',
        'allowed_countries',
        'excluded_countries',
    ];

    // Array casting logic
    protected $casts = [
        'allowed_countries'  => 'array',
        'excluded_countries' => 'array',
    ];
}
