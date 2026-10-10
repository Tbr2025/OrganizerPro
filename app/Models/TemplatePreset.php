<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Super Admin's switches for one ready-made design (the design itself lives in code). */
class TemplatePreset extends Model
{
    protected $fillable = ['key', 'is_active', 'is_premium', 'credit_cost', 'sort'];

    protected $casts = ['is_active' => 'boolean', 'is_premium' => 'boolean', 'credit_cost' => 'integer'];
}
