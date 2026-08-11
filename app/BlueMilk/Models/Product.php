<?php

namespace App\BlueMilk\Models;

use App\BlueMilk\Cloner\Cloneable;
use App\BlueMilk\Enums\PublishState;
use App\BlueMilk\Priority\HasPriority;
use Astrotomic\Translatable\Translatable;
use Butschster\Head\Contracts\MetaTags\SeoMetaTagsInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Lang;
use Mcamara\LaravelLocalization\Interfaces\LocalizedUrlRoutable;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\SchemaOrg\Schema;
use Spatie\Sitemap\Contracts\Sitemapable;
use Spatie\Sitemap\Tags\Url;
use Spatie\Tags\HasTags;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Carbon\Carbon;

class Product extends Model implements HasMedia, LocalizedUrlRoutable, SeoMetaTagsInterface, Sitemapable
{
    use Cloneable, HasPriority, HasTags, InteractsWithMedia, SoftDeletes, Translatable, HasSlug;

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'disposal' => 'array',
        'state' => PublishState::class,
    ];

    protected $clone_exempt_attributes = [
        'state',
    ];

    protected $cloneable_relations = [
        'translations',
    ];

    protected $with = ['translations'];

    public $translatedAttributes = ['locale', 'label', 'abstract', 'content', 'content_2', 'content_3', 'washing', 'meta_title', 'meta_description', 'tech_link', 'tech_1', 'tech_2', 'tech_3', 'tech_4', 'tech_5'];

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(ProductAward::class)->orderBy('sort_order');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }
    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    // public function related()
    // {
    //     return $this->belongsToMany('App\BlueMilk\Models\Product', 'related_products', 'product_id', 'related_id')->where('published', 1)->withPivot('sku_id');
    // }

    public function scopePublished($query)
    {
        return $query->where('state', PublishState::PUBLIC);
    }

    public function getTitle(): string
    {
        return $this->meta_title ?? $this->name;
    }

    public function getDescription(): string
    {

        return $this->meta_description ?? $this->abstract ?? '';
    }

    public function getKeywords()
    {
        return $this->meta_keywords ?? '';
    }

    protected function images(): Attribute
    {
        return Attribute::make(
            get: function () {
                $to_ret = [];
                $mediaItems = $this->getMedia('images');
                foreach ($mediaItems as $mediaItem) {
                    $to_ret[] = [
                        'id' => $mediaItem->id,
                        'filename' => $mediaItem->getFullUrl('webp'),
                        'alt' => $mediaItem->getCustomProperty('alt'),
                        'priority' => $mediaItem->getCustomProperty('alt'),
                    ];
                }

                return $to_ret;
            }
        );
    }

    public function registerMediaCollections(?Media $media = null): void
    {
        // cover
        $this->addMediaCollection('cover')->singleFile()->withResponsiveImages()->registerMediaConversions(function (Media $media) {
            $this->addMediaConversion('webp')->format('webp')->fit(Fit::Max, 600, 1500)->withResponsiveImages()->nonQueued();
        });

        // preview
        $this->addMediaCollection('preview')->singleFile()->withResponsiveImages()->registerMediaConversions(function (Media $media) {
            $this->addMediaConversion('webp')->format('webp')->fit(Fit::Contain, 1080, 1080)->withResponsiveImages()->nonQueued();
        });

        // main
        $this->addMediaCollection('images')->withResponsiveImages()->registerMediaConversions(function (Media $media) {
            $this->addMediaConversion('webp')->format('webp')->fit(Fit::Crop, 1920, 1920)->withResponsiveImages()->nonQueued();
        });

        $this->addMediaCollection('sizes')->singleFile();

        $this->addMediaCollection('pdf')->singleFile();

        $this->addMediaCollection('video')->singleFile();

        $this->addMediaCollection('render')->singleFile();
    }

    public function getLocalizedRouteKey($locale)
    {
        return $this->slug ?? '';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            return static::where('id', $value)->first() ?? abort(404);
        } else {
            return static::where('slug', $value)->first() ?? abort(404);
        }
    }

    public function toSchemaOrg()
    {
        $productUrl = route('catalogue.show', ['product' => $this->slug]);

        $schema = Schema::product()
            ->name($this->name)
            ->description($this->getDescription())
            ->url($productUrl);

        if ($this->code) {
            $schema->sku($this->code);
        }

        $images = [];
        foreach ($this->getMedia('images')->take(3) as $image) {
            $images[] = $image->getFullUrl('webp');
        }
        if (count($images) > 0) {
            $schema->image($images);
        }

        $brand = Schema::brand()->name(config('app.name'));
        $schema->brand($brand);

        if ($this->category) {
            $schema->category($this->category->name);
        }

        if ($this->skus->count() > 0) {
            $seller = Schema::organization()->name(config('app.name'));
            $currency = config('app.currency', 'EUR');

            $offers = [];
            $prices = [];

            foreach ($this->skus as $sku) {
                $price = round($sku->price - ($sku->price * ($sku->discount ?? 0) / 100), 2);
                $availability = ($sku->stock > 0)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock';

                $offer = Schema::offer()
                    ->url($productUrl)
                    ->priceCurrency($currency)
                    ->price($price)
                    ->availability($availability)
                    ->itemCondition('https://schema.org/NewCondition')
                    ->seller($seller);

                if ($sku->code) {
                    $offer->sku($sku->code);
                }

                $offers[] = $offer;
                $prices[] = $price;
            }

            if (count($offers) === 1) {
                $schema->offers($offers[0]);
            } else {
                $schema->offers(
                    Schema::aggregateOffer()
                        ->url($productUrl)
                        ->priceCurrency($currency)
                        ->lowPrice(min($prices))
                        ->highPrice(max($prices))
                        ->offerCount(count($offers))
                        ->offers($offers)
                );
            }
        }

        return $schema->toScript();
    }

    public function toSitemapTag(): Url | string | array
    {
        if ($this->state !== PublishState::PUBLIC) {
            return [];
        }

        return Url::create(route('catalogue.show', ['product' => $this->slug]))
            ->setLastModificationDate(Carbon::instance($this->updated_at));
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }
}
