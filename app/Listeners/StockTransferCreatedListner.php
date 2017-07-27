<?php

namespace App\Listeners;

use App\Events\StockTransferCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Repositories\Eloquent\GTOManifestRepository as ManifestRepository;
use App\Models\Admin\PickingManifest;


class StockTransferCreatedListner 
{
	public function __construct()
	{

	}

	public function handle(StockTransferCreated $event)
	{
		// \Log::info(print_r($event->stockTransfer, true));
		if($event->stockTransfer->do_type == 2)
		{
			$manifestRepo = new ManifestRepository;
	        $manifest = $manifestRepo->generateManifest($event->stockTransfer->id);
	    }
	}
}