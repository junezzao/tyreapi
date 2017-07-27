<?php

namespace App\Repositories;

use App\Repositories\Contracts\OAuthRepositoryContract;
use App\Repositories\Repository as Repository;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;

class OAuthRepository extends Repository implements OAuthRepositoryContract
{
    
	public function model()
    {
        return '\OAuthClient';
    }

    public function getOAuthClientObj()
    {
        $oauth_client = \OAuthClient::findOrFail(Authorizer::getResourceOwnerId());
        return $oauth_client->authenticatable;
    }
}
