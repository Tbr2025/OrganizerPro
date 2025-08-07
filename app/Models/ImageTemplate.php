<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImageTemplate extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'layout_json',
    'background_image',
    'category_id', // correct column name for the foreign key

    'overlay_image_path', // must be here!
  ];

  protected $casts = [
    'layout_json' => 'array',
  ];

  public function category()
  {
    return $this->belongsTo(ImageTemplateCategories::class, 'category_id');
  }
}
