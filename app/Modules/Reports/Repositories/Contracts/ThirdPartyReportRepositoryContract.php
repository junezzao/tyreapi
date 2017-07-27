<?php

namespace App\Modules\Reports\Repositories\Contracts;

use App\Repositories\Contracts\RepositoryContract;

interface ThirdPartyReportRepositoryContract extends RepositoryContract
{
	public function process(array $data);
}
