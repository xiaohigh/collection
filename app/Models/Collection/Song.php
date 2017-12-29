<?php

namespace App\Models\Collection;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    // 声明关系
    public function lyric()
    {
    	return $this->hasOne('App\Models\Collection\Lyric');
    }

    public function album()
    {
    	return $this->belongsTo('App\Models\Collection\Album');
    }
}
