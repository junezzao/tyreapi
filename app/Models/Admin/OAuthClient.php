<?php

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class OAuthClient extends BaseModel
{
    
    protected $table = 'oauth_clients';

    protected $primaryKey = 'id';

    protected $fillable = ['id', 'authenticatable_id', 'authenticatable_type', 'secret'];

    protected $morphClass = 'OAuthClient';
    
    /**
     * Since we have multiple parties connecting to our API
     * We are using Polymorphic Relations to accomodate all parties
     * into a single table i.e oauth_clients
     * 
     * @return object
     */
    public function getDates()
    {
        return [];
    }
    
    public function authenticatable()
    {
        return $this->morphTo();
    }
}
