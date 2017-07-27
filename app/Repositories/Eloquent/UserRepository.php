<?php
namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\UserRepository as UserRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Models\User;

class UserRepository extends Repository implements UserRepositoryInterface
{
	public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function model()
    {
    	return 'App\Models\User';
    }
}
