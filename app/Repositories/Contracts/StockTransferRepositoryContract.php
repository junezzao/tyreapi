<?php

namespace App\Repositories\Contracts;

interface StockTransferRepositoryContract
{
    public function createStockTransfer($inputs);
    public function receiveStockTransfer($id);
    public function apiResponse($id);
}
