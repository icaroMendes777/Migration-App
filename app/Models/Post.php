<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    //use HasFactory;

    protected $fillable = [
        'collection_id',
        'index',
        'title_pt',
        'title_pali',
        'text'
    ];


}
