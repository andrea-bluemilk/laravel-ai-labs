<?php

namespace App\BlueMilk\Models;

use App\BlueMilk\Cloner\Cloneable;
use App\BlueMilk\Cart\Contracts\Buyable;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Sku extends Model implements Buyable, HasMedia
{
    use Cloneable, InteractsWithMedia, SoftDeletes, Translatable;

    protected $guarded = [];

    public $translatedAttributes = ['locale', 'abstract', 'content'];

    protected $with = ['translations'];

    protected $appends = ['images'];

    protected $clone_exempt_attributes = [
        'slug', 'state',
    ];

    protected $cloneable_relations = [
        'translations',
    ];

    protected function price(): Attribute
    {
        return Attribute::make(
            get: static fn ($value) => $value / 100,
            set: static fn ($value) => $value * 100,
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);

    }

    public function attribute_options(): BelongsToMany
    {
        return $this->belongsToMany(AttributeOption::class);
    }

    /**
     * Restituisce una collezione di oggetti Eloquent Attribute collegati tramite le attribute_options.
     */
    public function attributes()
    {
        return \App\BlueMilk\Models\Attribute::whereIn('id', $this->attribute_options->pluck('attribute_id')->unique())->get();
    }

    public function getBuyableIdentifier($options = null): int
    {
        return $this->id;
    }

    public function getBuyableDescription($options = null): string
    {
        return trim($this->product->name.' '.$this->name);
    }

    public function getBuyablePrice($options = null): float
    {
        return $this->price - ($this->price * $this->discount / 100);
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
        $this->addMediaCollection('images')->withResponsiveImages()->registerMediaConversions(function (Media $media) {
            $this->addMediaConversion('webp')->format('webp')->fit(Fit::Crop, 1920, 1920)->withResponsiveImages()->nonQueued();
        });
    }
}
