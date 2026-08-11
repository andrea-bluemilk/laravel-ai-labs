<?php

namespace App\BlueMilk\Models;

use App\BlueMilk\Priority\HasPriority;
use Astrotomic\Translatable\Translatable;
use Butschster\Head\Contracts\MetaTags\SeoMetaTagsInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;
use Mcamara\LaravelLocalization\Interfaces\LocalizedUrlRoutable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Category extends Model implements HasMedia, LocalizedUrlRoutable, SeoMetaTagsInterface
{
    use HasPriority, InteractsWithMedia, SoftDeletes, Translatable;

    protected $guarded = [];

    protected $with = ['translations'];

    public $translatedAttributes = ['locale', 'name', 'abstract', 'description', 'slug', 'meta_title', 'meta_description'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getTitle(): string
    {
        return $this->meta_title ?? $this->name;
    }

    public function getDescription(): string
    {
        return $this->meta_description ?? $this->name;
    }

    public function getKeywords(): string
    {
        return $this->meta_keywords ?? '';
    }

    public function registerMediaCollections(?Media $media = null): void
    {
        // cover
        $this->addMediaCollection('cover')->singleFile()->withResponsiveImages()->registerMediaConversions(function (Media $media) {
            $this->addMediaConversion('webp')->format('webp')->fit(Fit::Crop, 1920, 1080)->background('FFFFFF')->withResponsiveImages()->nonQueued();
        });

        // preview
        $this->addMediaCollection('preview')->singleFile()->withResponsiveImages()->registerMediaConversions(function (Media $media) {
            $this->addMediaConversion('webp')->format('webp')->fit(Fit::Crop, 665, 795)->background('FFFFFF')->withResponsiveImages()->nonQueued();
        });
    }

    public function getLocalizedRouteKey($locale)
    {
        return $this->translate($locale)?->slug;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            return static::where('id', $value)->first() ?? abort(404);
        } else {
            return static::whereTranslation('slug', $value, Lang::getLocale())->first() ?? abort(404);
        }
    }
}
