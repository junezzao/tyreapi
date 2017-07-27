<?php

namespace App\Repositories\Contracts;

interface OAuthRepositoryContract extends RepositoryContract
{
    public function getOAuthClientObj();
}
