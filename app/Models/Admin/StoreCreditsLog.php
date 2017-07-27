<?php

use App\Models\BaseModel;

class StoreCreditsLog extends BaseModel
{
    protected $table = "store_credits_log";
    
    protected $primaryKey = "log_id";

    protected $fillable = ["sale_id", "amount", "member_id", "user_id", "remark", "sale_item_id"];
    
    public function getDates()
    {
        return [];
    }
    
    public function member()
    {
        return $this->belongsTo('Member', 'member_id');
    }
    
    public function admin()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
    
    public function sales()
    {
        return $this->belongsTo('Sales', 'sale_id');
    }
}
