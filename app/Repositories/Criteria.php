<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;

// use Bosnadev\Repositories\Contracts\RepositoryInterface;

abstract class Criteria
{

    /**
     * @param $model
     * @param RepositoryInterface $repository
     * @return mixed
     */
    abstract public function apply($model, RepositoryContract $repository);
}
