<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;

class ChannelType extends BaseModel
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'channel_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'status', 'fields', 'third_party', 'controller', 'site', 'manual_order', 'shipping_rate', 'type'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $casts = [
        'third_party' => 'boolean',
    ];

    /**
     *
     * Relationships
     *
     */

    public function channels()
    {
        return $this->hasMany('App\Models\Admin\Channel');
    }
}
