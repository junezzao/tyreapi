<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class PromotionCode extends Model
{
    
    protected $table = 'promotion_code';
    
    protected $primaryKey = "code_id";

    protected $fillable = [];
    
    protected $softDelete = true;
    
    protected $hidden = array("deleted_at", "created_at", "updated_at");

    protected $morphClass = 'PromotionCode';
    
    public function getDates()
    {
        return [];
    }
    
    public function promo()
    {
        return $this->belongsTo('Promotion', 'promotion_id');
    }
    
    public function order_items()
    {
        return $this->morphMany('Order', 'ref');
    }

    public function orderItems()
    {
        return $this->morphMany('App\Models\Admin\OrderItem', 'ref');
    }
    
    public function cart_items()
    {
        return $this->morphMany('CartItem', 'product');
    }
}
