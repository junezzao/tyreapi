<?php namespace App\Http\Traits;

use DB;
use Carbon\Carbon;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use App\Models\Admin\Order;
use App\Models\Admin\TaxInvoice;
use App\Models\Admin\CreditNote;
use App\Models\Admin\Channel;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Merchant;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\ReturnLog;
use App\Models\Admin\Document;
use App\Models\Media;
use App\Modules\ThirdParty\Http\Controllers\SellerCenterController;
use Storage;

trait DocumentGeneration
{
    private function getOrderDetails($orderId)
    {
        $order = Order::with('items', 'channel', 'member')->findOrFail($orderId);

        $promotions = array();
        $orderItems = array();
        foreach ($order->items as $item) {
            if ($item->ref_type == 'PromotionCode') {
                $promotions[] = $item->ref->promo_code;
            }

            $tp_extra = json_decode($order->tp_extra, true);
            if (array_key_exists('discount_codes', $tp_extra) && !empty($tp_extra['discount_codes']) ) {
                $promotions[] = $tp_extra['discount_codes'];
            }

            if ($item->ref_type == 'ChannelSKU') {
                $orderItems[] = $item;
            }
        }

        $order->promotions = (!empty($promotions) ? implode(', ', $promotions) : 'None');
        unset($order->items);
        $order->items = $orderItems;
        // issuing company
        $channel = Channel::find($order->channel_id);
        $issuingCompany = DB::table('issuing_companies')->where('id','=',$channel->issuing_company)->first();
        $issuingCompany->address = str_replace('\n','<br>', $issuingCompany->address);
        $issuingCompany->extra = json_decode($issuingCompany->extra);

        $order->issuingCompany = $issuingCompany;

        return $order;
    }
    private function sellerCenterGetDocument($orderId, $type)
    {
        $order = $this->getOrderDetails($orderId);
        $fileName = strtolower($type).'_' . $order->id . '_' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y-m-d')  . '.html';
        $s3path = 'documents/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y') . '/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('m') . '/' . $orderId . '/' . $fileName;

        $documents = Document::where('sale_id','=',$orderId)->where('document_type','=',$type)->get();
        if(count($documents) <= 0)
        {
            $thirdPartyController = $order->channel->channel_type->controller;
            $api = new $thirdPartyController;
            foreach($order->items as $item){
                $response = $api->getDocumentDemand($item->id,$order->channel_id, $type);
            }

            $documents = Document::where('sale_id','=',$orderId)->where('document_type','=',$type)->get();
        }
        // \Log::info(print_r($documents, true));
        // $filePath = 'app/'.strtolower($type).'/' . $fileName;
        // $pdf = \PDF::loadView('orders.'.strtolower($type), compact('order', 'documents'))->save(storage_path($filePath));
        $dir = strtolower($type);
        $filePath = 'app/'. $dir . '/'. $fileName;
        if(!is_dir(storage_path('app/'.$dir))) Storage::disk('local')->makeDirectory($dir);

        $content = view('orders.'.strtolower(($type == 'invoice' ? 'tp_invoice' : $type)), compact('order', 'documents'))->render();
        if($type == 'shippingLabel'){
            // if(($order->shipping_provider == 'SkyNet - DS' || $order->shipping_provider == 'Ta-Q-Bin')){
            //     $hardcodedFix = '<br><br><br><br><br><br><br><br><br><br><br><br><div class="page" style="page-break-before: always;">';
            //     $content = str_replace('<div class="page" style="page-break-before: always;">', $hardcodedFix, $content);
            // }else if($order->shipping_provider == 'GDEX-DS'){
            //         $hardcodedFix = '<br><br><br><br><br><br><br><br><br><br><br><br><div class="page">';
            //         $content = str_replace('<div class="page">', $hardcodedFix, $content);
            // }
        }
        file_put_contents(storage_path($filePath), $content);

        return $this->uploadFileToS3($fileName, strtolower($type) , $s3path);
    }


    public function generateShippingLabels($orderId) {
        return $this->sellerCenterGetDocument($orderId,'shippingLabel');
    }

    public function generateInvoices($orderId) {
        return $this->sellerCenterGetDocument($orderId,'invoice');
    }

    // get seller center invoice
    public function getInvoices($orderId) {
        $documents = Document::where('sale_id','=',$orderId)->where('document_type', '=', 'invoice')->get();
        if(count($documents) <= 0)
        {
            $order = $this->getOrderDetails($orderId);
            $thirdPartyController = $order->channel->channel_type->controller;
            $api = new $thirdPartyController;
            foreach($order->items as $item) {
                $response = $api->getDocumentDemand($item->id,$order->channel_id, 'invoice');
            }

            $documents = Document::where('sale_id','=',$orderId)->where('document_type','=', 'invoice')->get();
        }
        return $documents;
    }

    public function getNextNo($type, $prefix)
    {
        $running_number = DB::table('running_number')->where('type','=',$type)->where('prefix','=',$prefix)->first();
        if(intval($running_number->current_no)>=99999){
            DB::statement("update running_number set current_no=0 where type='$type' and prefix='$prefix';");
        }
        DB::statement("update running_number set current_no=last_insert_id(current_no+1) where type='$type' and prefix='$prefix';");
        return sprintf("%05d",DB::getPdo()->lastInsertId());
    }

    public function getTaxInvoiceNo($orderId)
    {
        $tax_invoice = DB::table('order_invoice')->where('order_id','=',$orderId)->first();
        if(is_null($tax_invoice))
        {
            $invoice = $this->generateTaxInvoice($orderId);
            $tax_invoice = DB::table('order_invoice')->where('order_id','=',$orderId)->first();
        }
        return !is_null($tax_invoice)?$tax_invoice->tax_invoice_no:null;
    }

    public function generateTaxInvoice($orderId)
    {
        $order = $this->getOrderDetails($orderId);
        $fileName = 'tax_invoice_' . $order->id . '_' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y-m-d')  . '.pdf';
        $s3path = 'documents/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y') . '/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('m') . '/' . $orderId . '/' . $fileName;

        if($this->checkS3Exists($s3path))
        {
            return ['success'=>true,'url'=>Storage::disk('s3')->url($s3path)];
        }

        $issuingCompany = $order->issuingCompany;
        $tax_invoice_no = $issuingCompany->prefix;
        $tax_invoice_no .= Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format($issuingCompany->date_format);
        $tax_invoice_no .= '-'.$this->getNextNo('tax_invoice', $issuingCompany->prefix);
        $order->tax_invoice_no = $tax_invoice_no;

        $tax_invoice = TaxInvoice::firstOrNew(['order_id'=> $orderId]);
        $tax_invoice->tax_invoice_no = $tax_invoice_no;
        $tax_invoice->save();

        $order->channel_type = $order->channel->name;
        $order->currency = (!empty($order->currency) ? $order->currency : $order->channel->currency);
        $order->shipping_address = $order->shipping_street_1 . ', ' . (!empty($order->shipping_street_2) ? ($order->shipping_street_2 . ', ') : '')
                                    . $order->shipping_city . ', ' . $order->shipping_postcode . ' ' . $order->shipping_state . ', '
                                    . $order->shipping_country;

        $dir = 'tax_invoice';
        $filePath = 'app/'. $dir . '/'. $fileName;
        if(!is_dir(storage_path('app/'.$dir))) Storage::disk('local')->makeDirectory($dir);

        if($issuingCompany->gst_reg == 1){
            $pdf = \PDF::loadView('orders.tax_invoice', compact('order', 'issuingCompany'))->save(storage_path($filePath));    
        }else{
            $pdf = \PDF::loadView('orders.invoice', compact('order', 'issuingCompany'))->save(storage_path($filePath));
        }
        

        return $this->uploadFileToS3($fileName, 'tax_invoice', $s3path);
    }

    public function generateInvoice($orderId)
    {
        $order = $this->getOrderDetails($orderId);
        $fileName = 'invoice_' . $order->id . '_' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y-m-d')  . '.pdf';
        $s3path = 'documents/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y') . '/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('m') . '/' . $orderId . '/' . $fileName;

        if($this->checkS3Exists($s3path))
        {
            return ['success'=>true,'url'=>Storage::disk('s3')->url($s3path)];
        }

        $issuingCompany = $order->issuingCompany;
        $tax_invoice_no = $issuingCompany->prefix;
        $tax_invoice_no .= Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format($issuingCompany->date_format);
        $tax_invoice_no .= '-'.$this->getNextNo('tax_invoice', $issuingCompany->prefix);
        $order->tax_invoice_no = $tax_invoice_no;

        $tax_invoice = TaxInvoice::firstOrNew(['order_id'=> $orderId]);
        $tax_invoice->tax_invoice_no = $tax_invoice_no;
        $tax_invoice->save();

        $order->channel_type = $order->channel->name;
        $order->currency = (!empty($order->currency) ? $order->currency : $order->channel->currency);
        $order->shipping_address = $order->shipping_street_1 . ', ' . (!empty($order->shipping_street_2) ? ($order->shipping_street_2 . ', ') : '')
                                    . $order->shipping_city . ', ' . $order->shipping_postcode . ' ' . $order->shipping_state . ', '
                                    . $order->shipping_country;

        $dir = 'tax_invoice';
        $filePath = 'app/'. $dir . '/'. $fileName;
        if(!is_dir(storage_path('app/'.$dir))) Storage::disk('local')->makeDirectory($dir);

        $pdf = \PDF::loadView('orders.invoice', compact('order', 'issuingCompany'))->save(storage_path($filePath));

        return $this->uploadFileToS3($fileName, 'tax_invoice', $s3path);
    }

    public function generateTaxInvoicePage($orderId)
    {
        $order = $this->getOrderDetails($orderId);
        $issuingCompany = $order->issuingCompany;

        $order->tax_invoice_no = $this->getTaxInvoiceNo($orderId);

        $order->channel_type = $order->channel->name;
        $order->currency = (!empty($order->currency) ? $order->currency : $order->channel->currency);
        $order->shipping_address = $order->shipping_street_1 . ', ' . (!empty($order->shipping_street_2) ? ($order->shipping_street_2 . ', ') : '')
                                    . $order->shipping_city . ', ' . $order->shipping_postcode . ' ' . $order->shipping_state . ', '
                                    . $order->shipping_country;

        return (string) view('orders.tax_invoice_page', compact('order', 'issuingCompany'));
    }

    public function generateCreditNoteItem($item_id)
    {
        $item = OrderItem::find($item_id);
    }

    public function printCreditNote($orderId)
    {
        $order = $this->getOrderDetails($orderId);

        $fileName = 'credit_note_' . $order->id . '_' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y-m-d')  . '.pdf';
        $s3path = 'documents/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y') . '/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('m') . '/' . $orderId . '/' . $fileName;
        if($this->checkS3Exists($s3path))
        {
            return ['success'=>true,'url'=>Storage::disk('s3')->url($s3path)];
        }
        return $this->generateCreditNote($orderId);

    }

    public function generateCreditNote($orderId) {

        $order = $this->getOrderDetails($orderId);

        $channel = Channel::find($order->channel_id);
        $issuingCompany = $order->issuingCompany;

        $order->tax_invoice_no = $this->getTaxInvoiceNo($orderId);
        $order->channel_type = $order->channel->name;
        $order->currency = (!empty($order->currency) ? $order->currency : $channel->currency);
        $order->shipping_address = $order->shipping_street_1 . ', ' . (!empty($order->shipping_street_2) ? ($order->shipping_street_2 . ', ') : '')
                                    . $order->shipping_city . ', ' . $order->shipping_postcode . ' ' . $order->shipping_state . ', '
                                    . $order->shipping_country;


        // get return_log for the order
        $returns  = ReturnLog::with('item')->where('order_id','=',$orderId)->get();
        if($returns->isEmpty()){
            return array('success' => false, 'error' => 'No returned/canceled.');
        }
        $items = array();
        foreach ($returns as $return)
        {
            $fileName = 'credit_note_' . $order->id . '_' . Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->format('Y-m-d')  . '.pdf';
            $s3path = 'documents/' . Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->format('Y') . '/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('m') . '/' . $orderId . '/' . $fileName;

            $item = new \stdClass();
            $item = json_decode($return->toJson());

            // check in order_credit_note
            $credit_note = CreditNote::where('order_id','=',$orderId)->where('order_items','=',$return->order_item_id)->first();
            if(!is_null($credit_note))
            {
                $item->credit_note_no = $credit_note->credit_note_no;
            }
            else
            {
                $item->credit_note_no = 'CN-'.$issuingCompany->prefix;
                $item->credit_note_no .= Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->format($issuingCompany->date_format);
                $item->credit_note_no .= '-'.$this->getNextNo('credit_note', $issuingCompany->prefix);
                $credit_note = new CreditNote;
                $credit_note->credit_note_no = $item->credit_note_no;
                $credit_note->tax_invoice_no = $order->tax_invoice_no;
                $credit_note->order_items = $return->order_item_id;
                $credit_note->order_id = $orderId;
                $credit_note->save();
            }
            $items[] = $item;
        }

        $dir = 'credit_note';
        $filePath = 'app/'. $dir . '/'. $fileName;
        if(!is_dir(storage_path('app/'.$dir))) Storage::disk('local')->makeDirectory($dir);

        $pdf = \PDF::loadView('orders.credit_note', compact('return', 'order', 'items','issuingCompany' ))->save(storage_path($filePath));

        return $this->uploadFileToS3($fileName, 'credit_note', $s3path);
    }

    public function uploadFileToS3($fileName, $dir, $s3path) {

        $uploadedfile = Storage::disk('local')->get($dir . '/' . $fileName);
        $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);

        if($s3upload) {
            Storage::disk('local')->delete($dir . '/' . $fileName);

            $media = new Media;
            $media->filename = $fileName;
            $media->ext = '.pdf';
            $media->media_url = Storage::disk('s3')->url($s3path);
            $media->media_key = $s3path;
            $media->save();

            return array('success' => true, 'url' => $media->media_url);
        }
        else {
            return array('success' => false, 'error' => 'Failed to upload file to S3.');
        }
    }

    public function checkS3Exists($file)
    {
        return Storage::disk('s3')->exists($file);
    }
}
