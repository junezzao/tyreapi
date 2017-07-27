<?php

namespace App\Repositories\Contracts;

interface ChannelRepositoryContract
{
    public function getChannelByType($type);

    public function getChannelProducts($dc_id);

    public function getChannelProduct($dc_id, $sku_id);

    public function productAPIResponse($channel_skus);

    public function install_webhook($ch_id, $inputs);

    public function update_webhook($wh_id, $inputs);

    public function get_webhooks($ch_id);

    public function getChannelByToken();
}
