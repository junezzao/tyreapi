<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomFieldsForChannelType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('channel_types')->where('name', 'Shopify')->where('id', 6)
            ->update(array('fields' => '[{"id":1,"label":"Store Type","api":"store_type","description":"POS or Webstore","default":"Webstore","required":"1"},{"id":2,"label":"Refund Applicable","api":"refund_applicable","description":"If set to True, customers paid by PayPal will get amount refunded to their PayPal account directly.","default":"False","required":"1"}]'));

        DB::table('channel_types')->where('name', 'Lelong')->where('id', 7)
            ->update(array('fields' => '[{"id":1,"label":"Default Store Category","api":"store_category","description":"Use Category ID from Store Categories tab.","default":"","required":"1"},{"id":2,"label":"My Shop Location","api":"state","description":"Acceptable values:\r\n\r\nJohor\r\nKedah\r\nKelantan\r\nKuala Lumpur\r\nLabuan\r\nMelaka\r\nNegeri Sembilan\r\nPahang\r\nPenang\r\nPerak\r\nPerlis\r\nPutrajaya\r\nSabah\r\nSarawak\r\nSelangor\r\nTerengganu\r\nOthers","default":"Selangor","required":"0"},{"id":3,"label":"Will Ship To","api":"ship_to","description":"Use the following code: \r\n\r\nR = Refer to Product Info\r\nL = Only same location\r\nS = Only same state\r\nP = Only Peninsular\r\nW = Within Sarawak\r\nB = Within Sabah\r\nT = Within East Malaysia\r\nM = Within Malaysia\r\nE = Within Asean\r\nA = Within Asia\r\nI = International","default":"M","required":"0"},{"id":4,"label":"Shipping Method","api":"shipping_method","description":"Use the following code:\r\n\r\nP = Courier service\r\nN = Normal post\r\nC = Self collect\r\nO = Others, if use \"O\", please follow it with the shipping method name\r\n\r\nExamples:\r\nP, N\r\nN, O, Poslaju","default":"P","required":"0"},{"id":5,"label":"Shipping Period","api":"ship_within","description":"Use the following code:\r\n\r\n1 = 1 working day\r\n2 = 2 working days\r\n3 = 1-3 days\r\n4 = 3-5 days\r\n5 = 5-7 days\r\n6 = 7-10 days","default":"4","required":"0"},{"id":6,"label":"Shipping Cost","api":"shipping_cost","description":"Format:\r\n\"Peninsular^Sabah\/Labuan^Sarawak^International^\"\r\n\r\nExamples:\r\n6^10^12^\r\n6^11^12^50^","default":"0^0^0^","required":"1"},{"id":7,"label":"Who Pay?","api":"who_pay","description":"Party who will bear the shipping cost. Use the following code:\r\n\r\nBP = Buyer pays\r\nSP = Seller pays","default":"BP","required":"0"},{"id":8,"label":"Payment Method","api":"payment_method","description":"Use the following code:\r\n\r\nB = Internet banking\r\nN = ATM \/ Bank in\r\nT = Bank transfer\r\nO = Cash on collection\r\nQ = Cheque\r\nC = Credit card\r\nS = SafeTrade\r\nH = Others, if use \"H\", please follow it with the payment method name\r\n\r\nExamples:\r\nN, T, C,\r\nB, N, T, C, S, H, PayPal","default":"B, C","required":"0"}]'));
    
        DB::table('channel_details')->where('channel_id', 15)
            ->update(array('extra_info' => '{"store_category":"211757","state":"Selangor","ship_to":"M","shipping_method":"P","ship_within":"4","shipping_cost":"0^0^0^","who_pay":"SP","payment_method":"B, C, T"}'));

        DB::table('channel_details')->where('channel_id', 35)
            ->update(array('extra_info' => '{"store_category":"0","state":"Selangor","ship_to":"M","shipping_method":"P","ship_within":"4","shipping_cost":"0^0^0^","who_pay":"BP","payment_method":"B, C"}'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('channel_types')->where('name', 'Shopify')->where('id', 6)
            ->update(array('fields' => null));

        DB::table('channel_types')->where('name', 'Lelong')->where('id', 7)
            ->update(array('fields' => null));

        DB::table('channel_details')->where('channel_id', 15)
            ->update(array('extra_info' => null));

        DB::table('channel_details')->where('channel_id', 35)
            ->update(array('extra_info' => null));
    }
}
