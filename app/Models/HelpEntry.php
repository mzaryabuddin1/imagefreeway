<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpEntry extends Model
{
    protected $fillable = ['slug', 'section', 'title', 'body', 'sort'];
}
