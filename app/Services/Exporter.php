<?php

namespace App\Services;

use App\BlueMilk\Models\Sku;

class Exporter
{
    public function __construct() {}


    public function ucpFeed()
    {
        $prodotti = Sku::with(['product.family', 'product.category', 'product.groups'])->whereHas('product', function ($q) {
            $q->published();
        })->get();

        $txt = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.'<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">'.PHP_EOL.'<channel>'.PHP_EOL.'<title>'.config('app.name').' UCP Catalog</title>'.PHP_EOL.'<link>'.config('app.url').'</link>'.PHP_EOL.'<description></description>'.PHP_EOL;

        foreach ($prodotti as $sku) {
            $txt .= '<item>'.PHP_EOL.'<g:id>'.$sku->code.'</g:id>'.PHP_EOL;
            $txt .= '<g:title>'.strip_tags(trim($sku->product->name)).'</g:title>'.PHP_EOL;
            $txt .= '<g:description>'.str_replace('&', '&amp;', html_entity_decode(strip_tags($sku->product->getDescription()))).'</g:description>'.PHP_EOL;
            $txt .= '<g:link>'.route('catalogue.show', ['product' => $sku->product->slug]).'</g:link>'.PHP_EOL;

            if ($sku->getFirstMedia('images')) {
                $txt .= '<g:image_link>'.$sku->getFirstMedia('images')->getFullUrl('webp').'</g:image_link>'.PHP_EOL;
            } elseif ($sku->product->getFirstMedia('images')) {
                $txt .= '<g:image_link>'.$sku->product->getFirstMedia('images')->getFullUrl('webp').'</g:image_link>'.PHP_EOL;
            } elseif ($sku->product->getFirstMedia('preview')) {
                $txt .= '<g:image_link>'.$sku->product->getFirstMedia('preview')->getFullUrl('webp').'</g:image_link>'.PHP_EOL;
            } elseif ($sku->product->getFirstMedia('cover')) {
                $txt .= '<g:image_link>'.$sku->product->getFirstMedia('cover')->getFullUrl('webp').'</g:image_link>'.PHP_EOL;
            }
            $availability = ($sku->stock > 0) ? 'in stock' : 'out of stock';
            $salePrice = round($sku->price - ($sku->price * ($sku->discount ?? 0) / 100), 2);

            $txt .= '<g:brand>'.config('app.name').'</g:brand>'.PHP_EOL;
            $txt .= '<g:google_product_category>'.$sku->product->families->first()?->google_category.'</g:google_product_category>'.PHP_EOL;
            if ($sku->product->category) {
                $txt .= '<g:product_type>'.$sku->product->category->name.'</g:product_type>'.PHP_EOL;
            }
            $txt .= '<g:mpn>'.$sku->code.'</g:mpn>'.PHP_EOL;
            $txt .= '<g:adult>no</g:adult>'.PHP_EOL;
            $txt .= '<g:is_bundle>no</g:is_bundle>'.PHP_EOL;
            $ageGroup = $sku->product->groups->contains('id', 3) ? 'kids' : 'adult';
            $txt .= '<g:age_group>'.$ageGroup.'</g:age_group>'.PHP_EOL;
            $txt .= '<g:condition>new</g:condition>'.PHP_EOL;
            $txt .= '<g:availability>'.$availability.'</g:availability>'.PHP_EOL;
            $txt .= '<g:price>'.$sku->price.' EUR</g:price>'.PHP_EOL;
            if ($sku->discount > 0) {
                $txt .= '<g:sale_price>'.$salePrice.' EUR</g:sale_price>'.PHP_EOL;
            }
            $txt .= '<g:native_commerce>enabled</g:native_commerce>'.PHP_EOL;
            $txt .= '</item>'.PHP_EOL;
        }

        $txt .= '</channel>'.PHP_EOL.'</rss>'.PHP_EOL;

        return $txt;
    }
}
