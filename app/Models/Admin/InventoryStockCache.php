<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class InventoryStockCache extends Model {
	protected $table = 'inventory_stock_cache';
    protected $primaryKey = 'id';

    protected $fillable = array('channel_sku_id','channel_sku_quantity','reserved_quantity','last_stock_updated_at','remarks');
}