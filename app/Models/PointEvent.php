<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['community_id', 'user_id', 'points'];
}
