<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function locations()
    {
        return $this->hasMany(PlayerLocation::class);
    }
    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
