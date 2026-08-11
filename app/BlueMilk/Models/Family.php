<?php

namespace App\BlueMilk\Models;

use App\BlueMilk\Priority\HasPriority;
use Astrotomic\Translatable\Translatable;
use Butschster\Head\Contracts\MetaTags\SeoMetaTagsInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;
use Mcamara\LaravelLocalization\Interfaces\LocalizedUrlRoutable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;
use Carbon\Carbon;

class Family extends Model implements HasMedia, LocalizedUrlRoutable, SeoMetaTagsInterface, Sitemapable
{
    use HasPriority, InteractsWithMedia, SoftDeletes, Translatable;

    protected $guarded = [];

    protected $with = ['translations'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Families belonging to the REGIONE group (region tag). */
    public function scopeRegions($query)
    {
        return $query->whereHas('group', fn ($q) => $q->where('code', 'REGIONE'));
    }

    /** Families belonging to the TIPO group (wine type tag). */
    public function scopeTypes($query)
    {
        return $query->whereHas('group', fn ($q) => $q->where('code', 'TIPO'));
    }

    public $translatedAttributes = ['locale', 'name', 'abstract', 'description', 'slug', 'meta_title', 'meta_description'];

    // public function products(): BelongsToMany
    // {
    //     return $this->belongsToMany(Product::class);
    // }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
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

    public function toSitemapTag(): Url | string | array
    {
        return Url::create(route('catalogue.family', ['family' => $this->slug]))
            ->setLastModificationDate(Carbon::instance($this->updated_at));
    }
}
