<?php

namespace App\BlueMilk\Models;

use App\BlueMilk\Enums\OrderState;
use App\BlueMilk\Enums\OrderReturnReason;
use App\BlueMilk\Payment\PayableOrder;
use Carbon\Carbon;
use Dom\Attr;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class Order extends Model implements PayableOrder
{
    use SoftDeletes;

    protected $casts = [
        'status' => OrderState::class,
        'ship_status' => OrderState::class,
        'return_reason' => OrderReturnReason::class,
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'return_requested_at' => 'datetime',
        'mailed_at' => 'datetime',
    ];

    protected $attributes = [
        'total' => 0,
        'tax' => 0,
        'shipping_cost' => 0,
        'payment_cost' => 0,
        'coupon_amount' => 0,
    ];

    protected $guarded = [];

    /**
     * Get the user that owns the item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the shipping address for the order
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class)->withTrashed();
    }

    /**
     * Get the invoice address for the order.
     */
    public function invoice_address(): BelongsTo
    {
        return $this->belongsTo(Address::class)->withTrashed();
    }

    /**
     * Get the payment method for the order.
     */
    public function payment_method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Get the shipping method for the order.
     */
    public function shipping_method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Get the coupon for the order.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function i_province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'invoice_province_id');
    }

    /**
     * Get the details of the order
     */
    public function order_details(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * @return string
     */
    public function getPaymentOrderId()
    {
        return $this->uuid;
    }

    /**
     * Should be in eurocents for most payments providers
     *
     * @return float
     */
    public function getPaymentAmount()
    {
        return ($this->total + $this->shipping_cost + $this->payment_cost + $this->country_cost - $this->coupon_amount) * 100;
    }

    public function getShippingAmount()
    {
        return $this->shipping_cost * 100;
    }

    /**
     * @return string
     */
    public function getPaymentDescription()
    {
        return 'Ordine nr. '.$this->id;
    }

    /**
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->user ? $this->user->email : $this->email;
    }

    /**
     * @return string
     */
    public function getCustomerName()
    {
        return $this->user ? $this->user->surname.','.$this->user->name : $this->surname.','.$this->name;
    }

    /**
     * @return string
     */
    public function getCustomerLanguage()
    {
        return App::getLocale();
    }

    /**
     * @param  string
     */
    public function setPaymentUid($payment_id): PayableOrder
    {
        $this->payment_uid = $payment_id;
        $this->save();

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentUid()
    {
        return $this->payment_uid;
    }

    public function grossTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total + $this->shipping_cost + $this->payment_cost + $this->country_cost - $this->coupon_amount,
        );
    }

    public function canReturn(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->status == OrderState::DELIVERED || $this->status == OrderState::PAID) && !$this->return_requested_at && Carbon::now()->diffInDays($this->created_at) <= 20
        );
    }

    public function scopePositive($query)
    {
        return $query->where('status', '!=', 'new')->where('status', '!=', 'cancelled');
    }

    }
