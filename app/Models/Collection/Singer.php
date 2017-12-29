<?php

namespace App\Models\Collection;

use Illuminate\Database\Eloquent\Model;

class Singer extends Model
{
    //
    public function albums()
    {
    	return $this->hasMany('App\Models\Collection\Album');
    }
}
