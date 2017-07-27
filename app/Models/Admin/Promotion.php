<?php

class Promotion extends \Eloquent
{
    protected $table = 'promotions';
    
    protected $softDelete = true;
    
    protected $primaryKey = 'promotion_id';
    
    protected $fillable = ["channel_id","promotion_name", "promotion_prefix", "free_shipping", "discount_type", "requirement_type","requirement_amount","requirement_quantity","promotion_active", "is_percentage", "discount_amount", "discount_quantity"];
    
    protected $hidden = array("deleted_at");

    public function channel()
    {
        return $this->belongsTo('Channel', 'channel_id');
    }
    
    public function getDates()
    {
        return [];
    }
    
    public function scopeCartRequirement($query)
    {
        return $query->where('requirement_type', Config::get('globals.promo_type.cart'));
    }
    
    public function scopeItemRequirement($query)
    {
        return $query->where('requirement_type', Config::get('globals.promo_type.item'));
    }
    
    public function scopeCartDiscount($query)
    {
        return $query->where('discount_type', Config::get('globals.promo_type.cart'));
    }
    
    public function scopeItemDiscount($query)
    {
        return $query->where('discount_type', Config::get('globals.promo_type.item'));
    }
    
    public function requirement_items()
    {
        return $this->hasMany('PromoRequirement', 'promotion_id')
        ->select('requirement_id', 'promotion_id', 'product_name', 'products.product_id', 'media_path')
        ->join('products', 'promo_requirements.product_id', '=', 'products.product_id')
        ->leftjoin('product_media', 'products.default_media', '=', 'media_id');
    }
    
    public function discount_items()
    {
        return $this->hasMany('DiscountProduct', 'promotion_id')
        ->select('discount_id', 'promotion_id', 'product_name', 'products.product_id', 'media_path')
        ->join('products', 'discount_product.product_id', '=', 'products.product_id')
        ->leftjoin('product_media', 'products.default_media', '=', 'media_id');
        ;
    }
    
    public function code()
    {
        return $this->hasMany('PromotionCode', 'promotion_id')
        ->leftjoin('members', 'members.member_id', '=', 'promotion_code.member_id');
    }
}
