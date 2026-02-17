<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMeta extends Model
{
    // FluentCart order meta table
    protected $table = 'fct_order_meta';

    // Meta value automatic array/json casting
    protected $casts = [
        'meta_value' => 'array',
    ];
}
