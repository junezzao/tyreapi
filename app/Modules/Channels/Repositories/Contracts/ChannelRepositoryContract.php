<?php

namespace App\Modules\Channels\Repositories\Contracts;

use App\Repositories\Contracts\RepositoryContract;

interface ChannelRepositoryContract extends RepositoryContract
{
	public function getSyncHistory($data);
}
