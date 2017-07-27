<?php

namespace App\Repositories\Contracts;

interface OrderRepository extends RepositoryContract
{
	public function getOrderItems($order_id);

	public function processCancelReturn($data, $isCancel);

	public function getPromotionCodes($orderId);
}
