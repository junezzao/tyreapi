<?php
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\ChannelSKU;

class ChannelSkuSnapshot extends ChannelSKU
{
    protected $table = 'channel_sku_snapshot';
    protected $with = [];
}
