<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Faker\Factory as Faker;

class StoreFrontTest extends TestCase
{
    const SALES_CHANNEL_TYPE = 1; // Online Store

    
    private $testData;

    public function setUp()
    {
        parent::setUp();
        $this->createTestData();
        // \Log::info(print_r($this->testData->access_token,true));
    }

    /*
    public function testChannelOrigin()
    {
        $request = $this->get('/stores?'.http_build_query($this->testData->access_token),
            [
            'www-origin'=>$this->testData->channel->channel_web,
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('channel_name', $data);
    }
    */
    public function testGetSales()
    {
        $request = $this->get('/orders',
            [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('sales', $data);
    }

    public function testGetSaleById()
    {
        $request = $this->get('/orders/'.$this->testData->sale_id,
            [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateSale()
    {
        $request = $this->post('/orders', $this->fakeSalesInput(), [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token']
            ]);
        // \Log::info(json_encode($this->fakeSalesInput()));
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }
    /*
    public function testUpdateSale()
    {
        $request = $this->put('/sales/'.$this->testData->sale_id,$this->fakeSalesInput());
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        // print_r($data);
        $this->assertArrayHasKey('id', $data);
    }
    */

    public function testChannelProducts()
    {
        $request = $this->get('/products?limit=20',
            [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        // \Log::info(print_r(count($data['products']), true));
        $this->assertArrayHasKey('products', $data);
    }

    public function testChannelProductById()
    {
        // print_r(__function__);
        $request = $this->get('/products/'.$this->testData->product_id.'?size=2',
            [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }

    public function testSKU()
    {
        $request = $this->get('/sku/'.$this->testData->items[0]->sku_id,
            [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token']
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }
    /*
    public function testChannelSKU()
    {
        $request = $this->get('/channel_sku/'.$this->testData->items[0]->channel_sku_id.'?'.http_build_query($this->testData->access_token));
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }
    */

    public function testCreateWebhook()
    {
        $request = $this->post('/webhooks', $this->fakeWebhookInput()['create'], [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token']
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }

    public function testGetWebhook()
    {
        $request = $this->get('/webhooks/'.$this->testData->webhook_id, [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }

    public function testUpdateWebhook()
    {
        $request = $this->put('/webhooks/'.$this->testData->webhook_id, $this->fakeWebhookInput()['update'], [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            ]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('id', $data);
    }

    public function testDeleteWebhook()
    {
        $request = $this->delete('/webhooks/'.$this->testData->webhook_id, null, [
            'Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            ]);
        $data = json_decode($request->response->getContent(), true);
        \Log::info(print_r($data, true));
        $this->assertArrayHasKey('success', $data);
    }
    
    


    /*
    public function testChannelGetMenu()
    {
        // print_r(__function__);
        $request = $this->get('/stores/menu?'.http_build_query($this->testData->access_token),['www-origin'=>$this->testData->channel->channel_web]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('menus', $data);
        
    }

    public function testChannelGetOptions()
    {
        // print_r(__function__);
        $request = $this->get('/stores/filters?'.http_build_query($this->testData->access_token),['www-origin'=>$this->testData->channel->channel_web]);
        $data = json_decode($request->response->getContent(), true);
        // \Log::info(print_r($data, true));
        $this->assertArrayHasKey('filters', $data);
    } 
    */
     
    private function createTestData()
    {
        $client = factory(Client::class)->create([]);

        $sale_channel = factory(Channel::class)->make([
            'channel_name' => 'Test Sales Channel',
            'channel_type' => self::SALES_CHANNEL_TYPE,
        ]);
        
        $sale_channel2 = factory(Channel::class)->make([
            'channel_name' => 'Test Sales Channel2',
            'channel_type' => self::SALES_CHANNEL_TYPE,
        ]);
        

        $client->channels()->save($sale_channel);
        $client->channels()->save($sale_channel2);

        $id = sprintf("%6d", rand(100000, 999999));
        $oauth_client = factory(OAuthClient::class)->create(['id'=>$id, 'authenticatable_id'=>$sale_channel->channel_id, 'authenticatable_type'=>'Channel']);
        
        
        $menu = factory(Menu::class)->create([
            'channel_id'=>$sale_channel->channel_id,
            'menu_content'=>'[{"id":"j1_9","text":"Women","icon":"fa fa-folder","li_attr":{"id":"j1_9"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":true,"selected":false,"disabled":false},"data":null,"children":[{"id":"j1_14","text":"Tops","icon":"fa fa-file-text","li_attr":{"id":"j1_14"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Tops,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_15","text":"Skirts","icon":"fa fa-file-text","li_attr":{"id":"j1_15"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Skirts","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_16","text":"Dresses","icon":"fa fa-file-text","li_attr":{"id":"j1_16"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Dresses,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_17","text":"Pants","icon":"fa fa-file-text","li_attr":{"id":"j1_17"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Pants,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_18","text":"Shorts","icon":"fa fa-file-text","li_attr":{"id":"j1_18"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Shorts,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_19","text":"Jackets","icon":"fa fa-file-text","li_attr":{"id":"j1_19"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Jackets","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_44","text":"Bags","icon":"fa fa-file-text","li_attr":{"id":"j1_44"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Bags,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_45","text":"Shoes","icon":"fa fa-file-text","li_attr":{"id":"j1_45"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Shoes,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_76","text":"Wallets","icon":"fa fa-file-text","li_attr":{"id":"j1_76"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Wallets,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_37","text":"Necklaces","icon":"fa fa-file-text","li_attr":{"id":"j1_37"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Necklaces","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_33","text":"Socks","icon":"fa fa-file-text","li_attr":{"id":"j1_33"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Socks","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_43","text":"Frames","icon":"fa fa-file-text","li_attr":{"id":"j1_43"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Frames","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_46","text":"Sunglasses","icon":"fa fa-file-text","li_attr":{"id":"j1_46"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Sunglasses","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_47","text":"Bracelets","icon":"fa fa-file-text","li_attr":{"id":"j1_47"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Bracelets","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_48","text":"Watches","icon":"fa fa-file-text","li_attr":{"id":"j1_48"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Watches","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_49","text":"One-Piece","icon":"fa fa-file-text","li_attr":{"id":"j1_49"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"One-Piece,Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_50","text":"Earrings","icon":"fa fa-file-text","li_attr":{"id":"j1_50"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Earrings","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_51","text":"Anklets","icon":"fa fa-file-text","li_attr":{"id":"j1_51"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Anklets","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_57","text":"T-Shirts","icon":"fa fa-file-text","li_attr":{"id":"j1_57"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,T-Shirts","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_59","text":"Shirts","icon":"fa fa-file-text","li_attr":{"id":"j1_59"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Shirts","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_75","text":"Outerwear","icon":"fa fa-file-text","li_attr":{"id":"j1_75"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women,Outerwear","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_97","text":"Headwear","icon":"fa fa-file-text","li_attr":{"id":"j1_97"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Women","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"}],"type":"root"},{"id":"j1_4","text":"Men","icon":"fa fa-folder","li_attr":{"id":"j1_4"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":true,"selected":false,"disabled":false},"data":null,"children":[{"id":"j1_40","text":"T-Shirts","icon":"fa fa-file-text","li_attr":{"id":"j1_40"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"T-Shirts,Men","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_6","text":"Shirts","icon":"fa fa-file-text","li_attr":{"id":"j1_6"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Shirts","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_7","text":"Pants","icon":"fa fa-file-text","li_attr":{"id":"j1_7"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Pants,Men","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_8","text":"Shorts","icon":"fa fa-file-text","li_attr":{"id":"j1_8"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Shorts","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_10","text":"Sweaters","icon":"fa fa-file-text","li_attr":{"id":"j1_10"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Sweaters,Men","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_12","text":"Jackets","icon":"fa fa-file-text","li_attr":{"id":"j1_12"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Jackets","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_41","text":"Bags","icon":"fa fa-file-text","li_attr":{"id":"j1_41"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Bags,Men","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_42","text":"Shoes","icon":"fa fa-file-text","li_attr":{"id":"j1_42"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Shoes,Men","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_73","text":"Wallets","icon":"fa fa-file-text","li_attr":{"id":"j1_73"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Wallets","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_34","text":"Socks","icon":"fa fa-file-text","li_attr":{"id":"j1_34"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Socks","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_35","text":"Frames","icon":"fa fa-file-text","li_attr":{"id":"j1_35"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Frames","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_36","text":"Sunglasses","icon":"fa fa-file-text","li_attr":{"id":"j1_36"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Sunglasses","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_38","text":"Bracelets","icon":"fa fa-file-text","li_attr":{"id":"j1_38"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Bracelets","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_39","text":"Watches","icon":"fa fa-file-text","li_attr":{"id":"j1_39"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Watches","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_52","text":"Caps","icon":"fa fa-file-text","li_attr":{"id":"j1_52"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Caps","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_72","text":"Personal Care","icon":"fa fa-file-text","li_attr":{"id":"j1_72"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Men,Personal Care","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"}],"type":"root"},{"id":"j1_61","text":"Brands","icon":"fa fa-folder","li_attr":{"id":"j1_61"},"a_attr":{"href":"#","data-href":"pages/brands","data-tags":"","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":true,"selected":false,"disabled":false},"data":null,"children":[{"id":"j1_62","text":"Maximunity","icon":"fa fa-file-text","li_attr":{"id":"j1_62"},"a_attr":{"href":"#","data-href":"/brand/maximunity?brand=mx","data-tags":"","data-icon":"","data-banner":"https://lh5.googleusercontent.com/-FQHDyEY4QJY/VIq7NVGM3gI/AAAAAAAAANI/FcccZWooAQc/w1562-h600-no/banner-maximunity.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_64","text":"Subcrew","icon":"fa fa-file-text","li_attr":{"id":"j1_64"},"a_attr":{"href":"#","data-href":"/brand/subcrew?brand=sub","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/-4o8uFvGjekiheqyBH0EMYFgPr7qfsE4rsxIa_Iujwo=w1232-h474-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_65","text":"Good Pair","icon":"fa fa-file-text","li_attr":{"id":"j1_65"},"a_attr":{"href":"#","data-href":"/brand/good%20pair?brand=gp","data-tags":"","data-icon":"","data-banner":"https://lh5.googleusercontent.com/-FyJgAlLMOpw/VIq7NljrohI/AAAAAAAAAMU/Iq8R9uTd9TQ/w1562-h600-no/banner-good_pair.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_66","text":"Vision Streetwear","icon":"fa fa-file-text","li_attr":{"id":"j1_66"},"a_attr":{"href":"#","data-href":"/brand/vision%20streetwear?brand=vsw","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/-gCcsBtmF_2c/VIq7QFTv67I/AAAAAAAAANA/MUHYOeUxQ6g/w1562-h600-no/banner-vision_streetwear.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_67","text":"Medium rare","icon":"fa fa-file-text","li_attr":{"id":"j1_67"},"a_attr":{"href":"#","data-href":"/brand/medium%20rare?brand=mrg","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/-8oAFWyZ8J9M/VSzIgHo5A_I/AAAAAAAADZw/5E17WI-ROxM/w1816-h698-no/MEDIUMRARE-single-3.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_68","text":"Feistheist","icon":"fa fa-file-text","li_attr":{"id":"j1_68"},"a_attr":{"href":"#","data-href":"/brand/feistheist?brand=fh","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/-fITcmcUMVeo/VUwtAzuq-uI/AAAAAAAAGL8/1ub_8DTOBUE/w1818-h698-no/FH-single.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_70","text":"Yacht 21","icon":"fa fa-file-text","li_attr":{"id":"j1_70"},"a_attr":{"href":"#","data-href":"/brand/yacht%2021?brand=y21","data-tags":"","data-icon":"","data-banner":"https://lh6.googleusercontent.com/-st0BXy-mhMQ/VQuvHkceFGI/AAAAAAAAAYM/HRHC-HpTeG0/w1816-h698-no/Y21-single-page-banner.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_71","text":"Stoned & Co","icon":"fa fa-file-text","li_attr":{"id":"j1_71"},"a_attr":{"data-href":"/brand/stoned%20&%20co?brand=sc","data-tags":"","data-icon":"","data-banner":"http://i.imgur.com/oWeMuL4.png"},"state":{"loaded":true,"opened":false,"selected":true,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_78","text":"Commodity","icon":"fa fa-file-text","li_attr":{"id":"j1_78"},"a_attr":{"href":"#","data-href":"/brand/commodity?brand=cmd","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/ZmbaGC8h_IGfEHMzRTZKYRafoI2juJKPGLqzzhK53WA=w1562-h600-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_80","text":"EighteenInspiration","icon":"fa fa-file-text","li_attr":{"id":"j1_80"},"a_attr":{"href":"#","data-href":"/brand/eighteeninspiration?brand=eig","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/-8tPD3-A--7I/VL8U3q6RZxI/AAAAAAAAAJs/1NlHXyhsBsI/s1562/banner_18_inspiration.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_79","text":"Ellui","icon":"fa fa-file-text","li_attr":{"id":"j1_79"},"a_attr":{"href":"#","data-href":"/brand/ellui?brand=elu","data-tags":"","data-icon":"","data-banner":"https://lh6.googleusercontent.com/-SJCc93OFCis/VLywc-eW3ZI/AAAAAAAAAIA/88v18G7r9v0/s1562/banner_ellui.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_83","text":"Joe Chia","icon":"fa fa-file-text","li_attr":{"id":"j1_83"},"a_attr":{"href":"#","data-href":"/brand/joe%20chia?brand=jc","data-tags":"","data-icon":"","data-banner":"https://lh5.googleusercontent.com/-yLc7Tn5LgCg/VUrauZE-wlI/AAAAAAAAGCY/cDyPy5Li6Vo/w1818-h698-no/JOECHIA-single.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_84","text":"Milktee","icon":"fa fa-file-text","li_attr":{"id":"j1_84"},"a_attr":{"href":"#","data-href":"/brand/milktee?brand=mt","data-tags":"","data-icon":"","data-banner":"https://lh5.googleusercontent.com/-ax3vboSj4ks/VN4AlVultGI/AAAAAAAAAMY/ceqpIqKc7U4/s1562/banner_milktee_2.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_85","text":"Muuz","icon":"fa fa-file-text","li_attr":{"id":"j1_85"},"a_attr":{"href":"#","data-href":"/brand/muuz?brand=mz","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/x_aeujit2vCw1ChggGVvMoisNR6jjRhZGVFZDUacrnw=w1562-h600-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_87","text":"Nerd Unit","icon":"fa fa-file-text","li_attr":{"id":"j1_87"},"a_attr":{"href":"#","data-href":"/brand/nerd%20unit?brand=nrd","data-tags":"","data-icon":"","data-banner":"https://lh4.googleusercontent.com/-UCjiD5y66qU/VWwXv4REIUI/AAAAAAAAIko/wfzktBMxq4g/w1822-h700-no/single-NRD.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_89","text":"Status Anxiety","icon":"fa fa-file-text","li_attr":{"id":"j1_89"},"a_attr":{"href":"#","data-href":"/brand/status%20anxiety?brand=sta","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/-7h_h7hbv2aI/VLyg4Xx9YMI/AAAAAAAAAHA/I2JwLXrUFgo/s1562/banner_status_anxiety.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_90","text":"Protesta","icon":"fa fa-file-text","li_attr":{"id":"j1_90"},"a_attr":{"href":"#","data-href":"/brand/protesta?brand=pr","data-tags":"","data-icon":"","data-banner":"https://lh5.googleusercontent.com/-oLO99Z6F1Uk/VLyg3iyWJ3I/AAAAAAAAAGs/d5YkSd6xIO0/s1562/banner_protesta.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_92","text":"TUK","icon":"fa fa-file-text","li_attr":{"id":"j1_92"},"a_attr":{"href":"#","data-href":"/brand/tuk.?brand=tk","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/-Kq7lam1JISI/VLyg5HSKjgI/AAAAAAAAAHM/mUPMTTPOTPA/s1562/banner_tuk.jpgg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_93","text":"Supercrew","icon":"fa fa-file-text","li_attr":{"id":"j1_93"},"a_attr":{"href":"#","data-href":"/brand/supercrew?brand=scr","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/JrlytaOKgF8nZnC259sFuIxsUpNyJwexCtyiTsg-TNhsvfJjknwN9nGL3wyZ0P4-8JregfslukidQyPfIcphM8ErdTOIrosTmCKvHhTmZTp1bZ80XbEVBYERF7j8VWnTlh6SyJsb71kayg3MXBJfHt_gDVedhDPFIGY2_5XpP3klkjRJhd9NSeYM8YViYCHRtZUfEPHRo5DkSp_tNWuz6sash51od8cG7XDvAFB3yPjQxD5u8XK2de8ndTWe5eHo0krQzIWkSmFdO5oH0n4ihKQJnUXwSgP-Gt3rhkPW50Z1NxK5Kh66mS4iPvj64_YPHG_WJTF9fsIEBH1rwXgd3sIZYNOEaiOUAso6yfbOCzfVmNNJzUnlpPRYE81O-loFnZdZkXccZdcbM4gqCp7d6MynOtf3o8rL4jOSZLsKeaxO_TwW1Odxg5VmGEo2DmF2N3MoO0eOb5SM4t-IMdyQhmXANqXpYkFLMj5s7ThrPeJLIu8le7CirWs0r6dw0hY_Mp6K7iVYLXuQf81ZJCbHad4=w1366-h525-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_94","text":"Hypergrand","icon":"fa fa-file-text","li_attr":{"id":"j1_94"},"a_attr":{"href":"#","data-href":"/brand/hypergrand?brand=hyp","data-tags":"","data-icon":"","data-banner":"https://lh5.googleusercontent.com/-YX5VfD-OPwg/VL8kdH_160I/AAAAAAAAAKM/g3G_be2gGH0/s1562/banner_hyper_grand.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_104","text":"Pizzazz","icon":"fa fa-file-text","li_attr":{"id":"j1_104"},"a_attr":{"href":"#","data-href":"/brand/pizzazz?brand=jap","data-tags":"","data-icon":"","data-banner":"https://lh4.googleusercontent.com/-qj6SKwi4wTE/VVRDOAvlI9I/AAAAAAAAGsg/N81JKYrt4tE/w1820-h700-no/PZZ-black2-single.jpg"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_107","text":"Yezzo","icon":"fa fa-file-text","li_attr":{"id":"j1_107"},"a_attr":{"href":"#","data-href":"/brand/yezzo?brand=yez","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/mfr9VazEhaeGWNfe8ppPBqpZHVEoJNs3Y5KjA17il5s=w1562-h600-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_106","text":"Mastuli Khalid","icon":"fa fa-file-text","li_attr":{"id":"j1_106"},"a_attr":{"href":"#","data-href":"/brand/mastuli%20khalid?brand=mas","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/N-fIMdYeVc67BS5PbfnRcufgag_DeJIsMSypP_0OatM=w1562-h600-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_108","text":"Justin Chew","icon":"fa fa-file-text","li_attr":{"id":"j1_108"},"a_attr":{"href":"#","data-href":"/brand/justin%20chew?brand=jst","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/DSYrhHkobnUJ-P9uNKBPAXEjSbtSa6Ca2NBY-yTIt3I=w1366-h525-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_111","text":"By Invite Only","icon":"fa fa-file-text","li_attr":{"id":"j1_111"},"a_attr":{"href":"#","data-href":"/brand/by%20invite%20only?brand=bio","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/Sr2ZOSKJwu6T7TrhkH5m6Q3YtHTBohhuzsJso8hyHBQ=w1562-h600-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_112","text":"LANSI","icon":"fa fa-file-text","li_attr":{"id":"j1_112"},"a_attr":{"href":"#","data-href":"/brand/lansi?brand=lan","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/SUdIZIzGnZ3XWxefeUz5vZfXwo_754daUUMP7HjcPr4=w1562-h600-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_109","text":"OBEY","icon":"fa fa-file-text","li_attr":{"id":"j1_109"},"a_attr":{"href":"#","data-href":"/brand/obey?brand=oby","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/eIjIcYTjhubG7m-D40n0_tX3EqCf8g246kzXWTcQry0giYMld6iE3O2GX65NdH2QqA4WTxs-DxtAR7LEZIr1smQRifm6jhjQMKIbgZPBV72QJ_N9Xx5FeEYNKjRgDUWnb1fPK8VTEQyRuHLG-jtmKqA0XgEjPV1e71D-TyakP_wXmnrFp91jRC6lftxl1ylnGfJB4174EGd6cowMPpG7ObonNHlb8heCxmr2_RH2waSxmKF-5Z6LTHBG7pBQLgeJLj9LdGNoj7wRViUCo4whV41y9pgUKW7cZVIg2Yk0FsH2vIeeTHyfHe7zIFLixDLN9gtHGZrp_AjxJv0PM7hjgnctTxvjhkhqrvpFwRE57sIsUtSArBJ7NQ2ADFo0W1CzH7h-k_KnBwjz9pn-PSnZty1AMye76qvFRtp1SD8SGiukM1ZHyJ9pfeIlGESbwZJO_kPVHArBifpt8n8O1eFhH3OBw3AdF9FXlJ0BHI6p9A_4lAmcM9haY89vbJq7crlWZaZvDeyAiWMXTccvAVLMEsc=w1366-h525-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_115","text":"Subtle","icon":"fa fa-file-text","li_attr":{"id":"j1_115"},"a_attr":{"href":"#","data-href":"/brand/subtle?brand=ste","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/R-mP95B4s0uBKJQCKcUx6y6piRFIR8Om0BuEaaICeHYA0Bv59Tr0l1MzzHhVL6q62Zop1kU0JktTvAMDsPbF1vC_SH9FOxrBF5nc1erEt4jS_J7XG8VJT4UGMxSG29STpaP4Xe8SRYw28ZmPpqiMpVO_zXuwTbThfw_RsvNu53dSKLdAkHJrL41S-LDkhGsQd41S27iI28o1zD2M2czxwBq9YjZ1yeIztYj5XHX925NZZUzj2xXzg9k4oLhYXlmhGuqj5VdsqavmJKqhURa2aaEezV8rbiZYg9prMlKn-vlVvMpVm9edXGZSdClmLBoosd3O_A0NVuQ-zLOhMb1-z349XiTsXvUeJQD5bc7fAhsAOvb-BZuHkrbmEJ-9XrdTGX7hXMceZW1_8OlqD1y1sWuZP2j_Lir2qPxm_29ZRYXbpRVIljR7uK_2T3MvS6ULWa6hWLbQYDHhC2dde9GbhMxBHIrArQMesKU5HJblQfvLnm8YhE_7PdbZ8HdLH2QuT1_2fdm0nUFIg_yZq0PU_xA=w1366-h525-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_116","text":"Justin Chew x KOZO","icon":"fa fa-file-text","li_attr":{"id":"j1_116"},"a_attr":{"href":"#","data-href":"http://www.fabspy.com/brand/justinchewxkozo?brand=kjc","data-tags":"","data-icon":"","data-banner":"http://i.imgur.com/liPhWgQ.png"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"},{"id":"j1_117","text":"KOZO","icon":"fa fa-file-text","li_attr":{"id":"j1_117"},"a_attr":{"href":"#","data-href":"/brand/KOZO?brand=koz","data-tags":"","data-icon":"","data-banner":"https://lh3.googleusercontent.com/ofBCOFFCLfG01kErubQyuGmD8AuUe5hkqjqE_cBUwba1EfWGNQQJ3oIU55gQtmK0YrwPYtYEfYdVmHtQEFNK-_elh5MdEEA7UAk0LkQGPlbmBK4I69u7erromg5hSGWXhP90hOokokHgSv9AbGGBxjKiBkLaQhs3Y1SDD0z1rCynz7Tqd0ekrvfFcEQy7-eVcj0hSZc-GQjnY2XBbSVXFa4D9gZfh6qOuAIfaTVU2Cgo4rzKBRfyXBLJPVxBYA4AB0bdJKVasHcOllmBZNnY6OSj9oPdXbdnqj-QWfpInNufqjveHQ-bUeIc_E4UbPE8fQUSriX28pGUmtCE4dUz_t6Jnx7d03c-xPLJPQEbucTklxHfW585cb-Wtd7EGO7n9444pK8a1vOA9NmD4qOfSa8Hwu6yRfVwcOn9_oZcuSMucoTc5FAukOnB96Y60qs64TCnfqYZmbIY0oaOB6v6kEzLEscj1nEP1fWO_xYgQMdMJtvWjZJP107OABJ9B6YjOGNF7HRXHmtSGz5ZJLphZcc=w1366-h525-no"},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"file"}],"type":"root"},{"id":"j1_99","text":"Blog","icon":"fa fa-folder","li_attr":{"id":"j1_99"},"a_attr":{"href":"#","data-href":"http://blog.fabspy.com","data-tags":"","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"root"},{"id":"j1_110","text":"deRigr","icon":"fa fa-folder","li_attr":{"id":"j1_110"},"a_attr":{"href":"#","data-href":"http://derigr.com/","data-tags":"","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"root"},{"id":"j1_114","text":"Sale","icon":"fa fa-folder","li_attr":{"id":"j1_114"},"a_attr":{"href":"#","data-href":"/shop","data-tags":"Clearance","data-icon":"","data-banner":""},"state":{"loaded":true,"opened":false,"selected":false,"disabled":false},"data":null,"children":[],"type":"root"}]']);
        
        
        $brand = factory(Brand::class)->create();

        $product = factory(Product::class)->create(['client_id'=>$client->client_id, 'brand_id'=>$brand->brand_id]);
        $sku1 = factory(SKU::class)->make(['client_id'=>$client->client_id]);
        $sku2 = factory(SKU::class)->make(['client_id'=>$client->client_id]);
        $sku3 = factory(SKU::class)->make(['client_id'=>$client->client_id]);
        $sku4 = factory(SKU::class)->make(['client_id'=>$client->client_id]);
        
        $product->sku()->save($sku1);
        $product->sku()->save($sku2);
        $product->sku()->save($sku3);
        $product->sku()->save($sku4);
    
        $option1 = factory(SKUOption::class)->create();
        $option2 = factory(SKUOption::class)->create();

        $combination1 = factory(SKUCombination::class)->create(['option_id'=>$option1->option_id, 'sku_id'=>$sku1->sku_id]);
        $combination2 = factory(SKUCombination::class)->create(['option_id'=>$option2->option_id, 'sku_id'=>$sku2->sku_id]);
        $combination3 = factory(SKUCombination::class)->create(['option_id'=>$option2->option_id, 'sku_id'=>$sku3->sku_id]);
        $combination4 = factory(SKUCombination::class)->create(['option_id'=>$option2->option_id, 'sku_id'=>$sku4->sku_id]);

        $tag1 = factory(SKUTag::class)->create(['sku_id'=>$sku1->sku_id]);
        $tag2 = factory(SKUTag::class)->create(['sku_id'=>$sku2->sku_id]);

        $cs = array();

        $c_sku1 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel->channel_id,
            'sku_id'=>$sku1->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $cs[] = $c_sku1;
        $c_sku2 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel->channel_id,
            'sku_id'=>$sku2->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $cs[] = $c_sku2;
        
        $c_sku3 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel->channel_id,
            'sku_id'=>$sku3->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $cs[] = $c_sku3;
        
        $c_sku4 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel->channel_id,
            'sku_id'=>$sku4->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $cs[] = $c_sku4;
        

        
        $c_sku5 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel2->channel_id,
            'sku_id'=>$sku1->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $cs[] = $c_sku5;
        
        $c_sku6 = factory(ChannelSKU::class)->create([
            'channel_id'=>$sale_channel2->channel_id,
            'sku_id'=>$sku2->sku_id,
            'product_id'=>$product->product_id,
            'client_id'=>$client->client_id
        ]);
        $cs[] = $c_sku6;
        

        $sale = factory(Sales::class)->create([
            'client_id'=>$client->client_id,
            'channel_id'=>$sale_channel->channel_id,
            ]);
        $item1 = factory(SalesItem::class)->make(['product_id'=>$c_sku1->channel_sku_id]);
        $item2 = factory(SalesItem::class)->make(['product_id'=>$c_sku2->channel_sku_id]);
        $sale->items()->save($item1);
        $sale->items()->save($item2);

        $sale2 = factory(Sales::class)->create([
            'client_id'=>$client->client_id,
            'channel_id'=>$sale_channel->channel_id,
            ]);

        $item3 = factory(SalesItem::class)->make(['product_id'=>$c_sku3->channel_sku_id]);
        $item4 = factory(SalesItem::class)->make(['product_id'=>$c_sku4->channel_sku_id]);
        $sale2->items()->save($item3);
        $sale2->items()->save($item4);

        $sale3 = factory(Sales::class)->create([
            'client_id'=>$client->client_id,
            'channel_id'=>$sale_channel->channel_id,
            ]);

        $item5 = factory(SalesItem::class)->make(['product_id'=>$c_sku5->channel_sku_id]);
        $item6 = factory(SalesItem::class)->make(['product_id'=>$c_sku6->channel_sku_id]);
        $sale3->items()->save($item5);
        $sale3->items()->save($item6);

        $webhook = factory(Webhook::class)->create(['channel_id'=>$sale_channel->channel_id]);

        // print_r($sale->toArray());

        $response = new \stdClass();
        $response->webhook_id = $webhook->webhook_id;
        $response->channel = $sale_channel;
        $response->product_id = $product->product_id;
        $response->sku_id = $sku1->sku_id;
        $response->sale_id = $sale->sale_id;
        $response->items = $cs;

        $request = $this->post('/oauth/access_token', [
            'client_id'=>$id,
            'client_secret'=>$oauth_client->secret,
            'grant_type' => 'client_credentials'
        ]);
        $data = json_decode($request->response->getContent(), true);
        $response->access_token = ['access_token'=>$data['access_token']];
        
        $this->testData =  $response;

        // sleep(10);

        // \Log::info(print_r($this->testData, true));
    }

    public function fakeWebhookInput()
    {
        $data['create'] = [
            // 'HTTP_Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            'topic' => 'orders/created',
            'address'=> 'http://requestb.in/1de80jr1',
            'format'=> 'json'
        ];
        $data['update'] = [
            // 'HTTP_Authorization'=>'Bearer '.$this->testData->access_token['access_token'],
            'topic' => 'orders/created',
            'address'=> 'http://requestb.in/1de80jr1/updated',
            'format'=> 'json'
        ];
        return $data;
    }

    public function fakeSalesInput()
    {
        $faker = Faker::create();
        $items =  array();
        $sale_total = 0;
        for ($i=rand(2, 3);$i<count($this->testData->items);$i++) {
            $testData = $this->testData->items[$i-1];
            $item = [
                        'sku_id' => $testData->sku_id,
                        'price' => $testData->channel_sku_price,
                        'quantity' => 1,
                        'discount' => 1
                    ];
            $sale_total+=$testData->channel_sku_price;
            $items[] = $item;
        }
        $genders = ['M','F'];
        $gender = $genders[rand(0, 1)];
        $member= [
                'email' => $faker->freeEmail,
                'name' => $faker->name($gender?'male':'female'),
                'gender' => $gender,
                'type' => 0,
                'client_id' => $this->testData->channel->client_id,
                'channel_id' => $this->testData->channel->channel_id,
                'mobile' => $faker->phoneNumber,
                'birthday' => $faker->date,
                ];
        $shipping_info = [
                'recipient' => $member['name'],
                'address_1' => $faker->streetAddress,
                'address_2' => '',
                'phone' => $faker->phoneNumber,
                'postcode' => $faker->postcode,
                'city' => $faker->city,
                'state' => $faker->state,
                'country' => $faker->country,
                'tracking_no' => $faker->ean8,
                ];
        return [
                'payment_type'=> 'ipay',
                'total_price'=> $sale_total,
                'shipping_fee'=>$faker->randomFloat(2, 5, 10),
                'order_date'=>date('Y-m-d H:i:s'),
                'status' => 'paid',
                'total_discount'=>$faker->randomFloat(2, 5, 15),
                'reference_id' => $faker->randomNumber(7),
                'items' => $items,
                'customer' => $member,
                'shipping_info' => $shipping_info

            ];
    }
}
