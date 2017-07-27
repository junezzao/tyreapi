<?php

namespace App\Repositories\Contracts;

interface PartnerRepositoryContract
{
    public function getDistributionCenters($partner_id);
    public function getDistributionCenter($dc_id, $partner_id);
    public function prepareStockTransferDetails($client);
    public function getDistributionCenterDetails($dc_id, $partner_id, array $fields);
    public function distributionCenterAPIResponse($data);
    public function getPartnerOrders($partner_id);
    public function getDistributionCenterOrders($dc_id, $partner_id);
    public function getOrder($order_id, $partner_id);
    public function updateOrder($order_id, $inputs, $partner_id);
    public function returnOrder($order_id, $inputs, $partner_id);
}
