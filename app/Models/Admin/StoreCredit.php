<?php

namespace App\Models\Admin;

class StoreCredit extends Sales
{
    public function getDates()
    {
        return [];
    }
    
    public function sales_items()
    {
        return $this->morphOne('SalesItem', 'product');
    }
}
