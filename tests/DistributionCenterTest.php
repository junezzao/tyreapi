<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DistributionCenterTest extends TestCase
{
    const DISTRIBUTION_CENTER_TYPE = 11;
    const SALES_CHANNEL_TYPE = 6;

    // private $testData;

    // use WithoutMiddleware;
    private $testData;

    public function setUp()
    {
        parent::setUp();
        $this->createPartnerWithDistributionCenter();
    }

    
    public function testGetPartnerDistributionCenters()
    {
        $request = $this->get('/distribution_centers', ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('distribution_centers', $data);
    }

    public function testGetPartnerDistributionCenter()
    {
        $request = $this->get('/distribution_centers/'.$this->testData->dc_id1, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('distribution_center_id', $data);
    }

    public function testPostPartnerStockTransfer()
    {
        $form_params = array(
            'source_id' => $this->testData->dc_id1,
            'recipient_id' => $this->testData->dc_id2,
            'sku_list' => [
                ['sku_id'=> $this->testData->sku_id1 , 'quantity' => 1]
            ],
        );
        $request = $this->post('/distribution_centers/transfers', $form_params, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('transfer', $data);
    }

    public function testGetPartnerDistributionCenterProducts()
    {
        $request = $this->get('/distribution_centers/'.$this->testData->dc_id1.'/products', ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('products', $data);
    }

    public function testGetPartnerDistributionCenterProduct()
    {
        $request = $this->get('/distribution_centers/'.$this->testData->dc_id1.'/products/'.$this->testData->sku_id1, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('sku_id', $data);
    }

    public function testPostDistributionReplenishment()
    {
        $form_params = array(
            'sku_list' => [
                ['sku_id'=> $this->testData->sku_id1 , 'quantity' => 1]
            ],
        );
        $request = $this->post('/distribution_centers/'.$this->testData->dc_id1.'/replenish', $form_params, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('replenishment', $data);
    }

    public function testGetPartnerOrders()
    {
        $request = $this->get('/distribution_centers/orders', ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('orders', $data);
    }

    public function testGetPartnerOrder()
    {
        $request = $this->get('/distribution_centers/orders/'.$this->testData->order_id1, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('order_id', $data);
    }

    public function testPostPartnerUpdateOrder()
    {
        $form_params = array(
            'order_status' => 'shipped',
            'tracking_number'=> '0000001'
        );
        $request = $this->post('/distribution_centers/orders/'.$this->testData->order_id1.'/update', $form_params, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('order_id', $data);
    }

    public function testPostPartnerReturnOrder()
    {
        $form_params = array(
            'return_items' => [
                ['item_id' => $this->testData->item_id1,'quantity' => 1]
            ]
        );
        $request = $this->post('/distribution_centers/orders/'.$this->testData->order_id1.'/return', $form_params, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('order_id', $data);
    }

    public function testPostDistributionCenterInstallWebhook()
    {
        $form_params = array(
            'event' => 'orders/created',
            'url'=> 'http://requestb.in/15iidt11'
        );
        $request = $this->post('/distribution_centers/'.$this->testData->dc_id1.'/webhooks', $form_params, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('webhook_id', $data);
    }

    public function testPostDistributionCenterUpdateWebhook()
    {
        $form_params = array(
            'event' => 'transfers/approved',
            'url'=> 'http://requestb.in/1js3wm31'
        );
        $request = $this->post('/distribution_centers/'.$this->testData->dc_id1.'/webhooks/'.$this->testData->webhook_id.'/update', $form_params, ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('webhook_id', $data);
    }

    public function testGetDistributionWebhooks()
    {
        $request = $this->get('/distribution_centers/'.$this->testData->dc_id1.'/webhooks', ['partnerid'=>$this->testData->partner_id]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('webhooks', $data);
    }





    /*
        Test Data
    */
    private function createPartnerWithDistributionCenter()
    {
        $partner = factory(Partner::class)->create([]);

        $client = factory(Client::class)->create([]);

        $oauth_client = factory(OAuthClient::class)->create(['authenticatable_id'=>$partner->partner_id, 'authenticatable_type'=>'Partner']);
        
        $dc_channel1 = factory(Channel::class)->make([
            'channel_name'=> 'Test DistributionCenter',
            'channel_type' => self::DISTRIBUTION_CENTER_TYPE,
        ]);
        $dc_channel2 = factory(Channel::class)->make([
            'channel_name'=> 'Test DistributionCenter 2',
            'channel_type' => self::DISTRIBUTION_CENTER_TYPE,
        ]);

        $sale_channel = factory(Channel::class)->make([
            'channel_name' => 'Test Sales Channel',
            'channel_type' => self::SALES_CHANNEL_TYPE,
        ]);
        $sale_channel2 = factory(Channel::class)->make([
            'channel_name' => 'Test Sales Channel 2',
            'channel_type' => self::SALES_CHANNEL_TYPE,
        ]);


        $client->channels()->save($dc_channel1);
        $client->channels()->save($dc_channel2);
        $client->channels()->save($sale_channel);
        $client->channels()->save($sale_channel2);

        $dc1 = factory(DistributionCenter::class)->make([
            'default_sales_ch_id' => $sale_channel->channel_id,
            'distribution_ch_id' => $dc_channel1->channel_id
        ]);

        $dc2 = factory(DistributionCenter::class)->make([
            'default_sales_ch_id' => $sale_channel2->channel_id,
            'distribution_ch_id' => $dc_channel2->channel_id
        ]);

        $webhook = factory(Webhook::class)->create(['channel_id'=>$dc1->distribution_ch_id]);
        

        $partner->distribution_center()->save($dc1);
        $partner->distribution_center()->save($dc2);

        $brand = factory(Brand::class)->create();

        $product = factory(Product::class)->create(['client_id'=>$client->client_id, 'brand_id'=>$brand->brand_id]);
        $sku1 = factory(SKU::class)->make(['client_id'=>$client->client_id]);
        $sku2 = factory(SKU::class)->make(['client_id'=>$client->client_id]);

        $product->sku()->save($sku1);
        $product->sku()->save($sku2);

        $option1 = factory(SKUOption::class)->create();
        $option2 = factory(SKUOption::class)->create();

        $combination1 = factory(SKUCombination::class)->create(['option_id'=>$option1->option_id, 'sku_id'=>$sku1->sku_id]);
        $combination2 = factory(SKUCombination::class)->create(['option_id'=>$option2->option_id, 'sku_id'=>$sku2->sku_id]);

        $tag1 = factory(SKUTag::class)->create(['sku_id'=>$sku1->sku_id]);
        $tag2 = factory(SKUTag::class)->create(['sku_id'=>$sku2->sku_id]);

        $c_sku1 = factory(ChannelSKU::class)->create([
            'channel_id'=>$dc_channel1->channel_id,
            'sku_id'=>$sku1->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $c_sku2 = factory(ChannelSKU::class)->create([
            'channel_id'=>$dc_channel2->channel_id,
            'sku_id'=>$sku2->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);

        $c_sku3 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel->channel_id,
            'sku_id'=>$sku1->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $c_sku4 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel->channel_id,
            'sku_id'=>$sku2->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);

        $c_sku5 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel2->channel_id,
            'sku_id'=>$sku1->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $c_sku6 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel2->channel_id,
            'sku_id'=>$sku2->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);

        $sale = factory(Sales::class)->create([
            'client_id'=>$client->client_id,
            'channel_id'=>$sale_channel->channel_id,
            'distribution_ch_id'=> $dc1->distribution_ch_id
            ]);
        $item1 = factory(SalesItem::class)->make(['product_id'=>$c_sku3->channel_sku_id]);
        $item2 = factory(SalesItem::class)->make(['product_id'=>$c_sku4->channel_sku_id]);
        $sale->items()->save($item1);
        $sale->items()->save($item2);

        $sale2 = factory(Sales::class)->create([
            'client_id'=>$client->client_id,
            'channel_id'=>$sale_channel2->channel_id,
            'distribution_ch_id'=> $dc2->distribution_ch_id
            ]);
        $item3 = factory(SalesItem::class)->make(['product_id'=>$c_sku5->channel_sku_id]);
        $item4 = factory(SalesItem::class)->make(['product_id'=>$c_sku6->channel_sku_id]);
        $sale2->items()->save($item3);
        $sale2->items()->save($item4);

                
        $response = new \stdClass();
        $response->partner_id = $partner->partner_id;
        $response->dc_ch_id1 = $dc1->distribution_ch_id;
        $response->dc_ch_id2 = $dc2->distribution_ch_id;
        $response->dc_id1 = $dc1->distribution_center_id;
        $response->dc_id2 = $dc2->distribution_center_id;
        $response->sku_id1 = $sku1->sku_id;
        $response->sku_id2 = $sku2->sku_id;
        $response->order_id1 = $sale->sale_id;
        $response->item_id1 = $item1->item_id;
        $response->webhook_id = $webhook->webhook_id;
        $this->testData =  $response;
    }
}
