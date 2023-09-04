<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\wp_terms;

class wp_term_taxonomy extends Model
{

    protected $table = 'wp_term_taxonomy';
    public $timestamps = false;

    protected $fillable = [
        'term_taxonomy_id',
        'term_id',
        'taxonomy',
        'description',
        'parent',
        'count'
    ];



    public function term(): HasOne
    {
        return $this->hasOne(wp_terms::class);
    }
}
