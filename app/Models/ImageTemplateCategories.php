<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageTemplateCategories extends Model
{
    protected $fillable = ['name'];

    public function templates()
    {
        return $this->hasMany(ImageTemplate::class, 'category_id');
    }
}
