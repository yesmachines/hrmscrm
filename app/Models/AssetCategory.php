<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category', 'shortcode', 'status'])]
class AssetCategory extends Model
{
    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AssetCategory $cat) {
            if (empty($cat->shortcode)) {
                $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $cat->category);
                $base = strtoupper(substr($clean, 0, 4)) ?: 'CAT';
                $code = $base;
                $i = 1;
                while (static::where('shortcode', $code)->exists()) {
                    $code = $base.$i++;
                }
                $cat->shortcode = $code;
            }
        });
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AssetRequest::class, 'category_id');
    }
}
