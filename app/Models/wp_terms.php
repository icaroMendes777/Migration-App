<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wp_terms extends Model
{

    public $timestamps = false;

    protected $table = 'wp5lmt_terms';

    protected $fillable = [
        'name',
        'slug',
        'term_group'
    ];


}
