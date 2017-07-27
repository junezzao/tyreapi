<?php

namespace App\Repositories\Contracts;

interface ManifestRepository extends RepositoryContract
{
	public function generateManifest($creator_id);

    public function search($request);

    public function pickUpManifest($request);

    // show picking items
    public function pickingItems($id);

    // function for handling scanning hubwire sku
    public function pickItem($request, $id);

    // to inform the system that the picking item is out of stock
    public function outOfStock($request, $id);

    // Mark manifest as complete
    public function completed($id);
}
