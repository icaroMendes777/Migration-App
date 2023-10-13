<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wp_term_relationships extends Model
{
    public $timestamps = false;


    protected $table = 'wp_term_relationships';


    protected $fillable = [
        'object_id',
        'term_taxonomy_id',
        'term_order'
    ];
}
