<?php

namespace App\Models\Admin;

use App\Models\Admin\QuantityLogApp;

class Unknown extends QuantityLogApp
{
    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }
}
