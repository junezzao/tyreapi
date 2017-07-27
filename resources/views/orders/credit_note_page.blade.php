        <table border="0" cellspacing="1" cellpadding="0" width="100%">
            <tr>
                <td valign="top" colspan="5"><h1>CREDIT NOTE <br>{{ $item->credit_note_no }}</h1></td>
                <td valign="top" align="right" colspan="4" rowspan="2">
                    <p><img src="{{ asset($issuingCompany->logo_url) }}" class="img-responsive center-block" /></p>
                    <p class="small"><strong>{{ $issuingCompany->name }}</strong>
                        <br>{!! $issuingCompany->address !!}
                    @if(!empty($issuingCompany->extra))
                        @foreach($issuingCompany->extra as $k => $v)
                        <br>{{$k}} : {{$v}}
                        @endforeach
                    @endif
                    <br/>GST Number: {{ $issuingCompany->gst_reg_no }}</p>
                </td>
            </tr>
            <tr>
                <td valign="top" colspan="5">
                    <p><u>Order Details</u><br/>
                        <p><strong>Tax Invoice:</strong> {{ $order->tax_invoice_no }}</p>
                        <p><strong>Hubwire Order:</strong> #{{ $order->id }}</p>
                        <p><strong>Third Party Order:</strong> {{ (!empty($order->tp_order_code) ? $order->tp_order_code : 'N/A') }} <span class="channel_type">{{ $order->channel_type }}</span></p>
                        <p><strong>Date:</strong> {{ Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $return->created_at)->format(config('globals.date_format_invoice')) }}</p>
                    </p>
                </td>
            </tr>
            <tr class="breaker">
                <td colspan="9"></td>
            </tr>
            <tr>
                <td valign="top" colspan="3">
                    <p>
                        <u>Shipping Details</u><br/>
                        <strong>Name:</strong> {{ ucwords($order->shipping_recipient) }}
                        <p><strong>Contact Number:</strong> {{ $order->shipping_phone }}</p>
                        <p><strong>Address:</strong> {{ $order->shipping_address }}</p>
                    </p>
                </td>
                <td valign="top" colspan="3">
                    <p>
                        <u>Billing Details</u><br/>
                        <strong>Name:</strong> {{ (!empty($order->member->member_name) ? ucwords($order->member->member_name) : '') }}
                        <p><strong>Email:</strong> {{ (!empty($order->member->member_email) ? $order->member->member_email : '') }}</p>
                        <p><strong>Contact Number:</strong> {{ (!empty($order->member->member_mobile) ? $order->member->member_mobile : '') }}</p>
                    </p>
                </td>
                <td valign="top" colspan="3">
                    <p>
                        <u>Payment Details</u><br/>
                        <strong>Payment Type:</strong> {{ $order->payment_type }}
                        <p><strong>Promotion Code:</strong> {{ $order->promotions }}</p>
                        <p><strong>Amount Paid:</strong> {{ $order->currency }} {{ number_format($order->total, 2) }}</p>
                    </p>
                </td>
            </tr>
            <tr>
                <td valign="middle" class="title">No.</td>
                <td valign="middle" colspan="2" class="title">SKU</td>
                <td valign="middle" colspan="3" class="title">Product Name</td>
                <td valign="middle" class="title">Quantity</td>
                <td valign="middle" class="title">Listing Price<br/><span class="small ita">(Inclusive GST)</span></td>
                <td valign="middle" class="title">Line Total<br/><span class="small ita">(Inclusive GST)</span></td>
            </tr>
            <tr>
                <td colspan="9"><hr/></td>
            </tr>
                <tr class="even breaker">
                    <td valign="middle">1</td>
                    <td valign="middle" colspan="2">{{ $return->item->ref->sku->hubwire_sku }}</td>
                    <td valign="middle" colspan="3">{{ $return->item->ref->product->name }}</td>
                    <td valign="middle" align="center">{{ $return->quantity }}
                    <td valign="middle" align="center">{{ $order->currency }} {{ ($return->item->sale_price > 0) ? number_format($return->item->sale_price, 2) : number_format($return->item->unit_price, 2) }}</td>
                    <td valign="middle" align="center">{{ $order->currency }} {{ number_format($return->item->sold_price * $return->item->original_quantity + (($return->item->tax_inclusive) ? 0 : $return->item->tax), 2) }}</td>
                </tr>
               <tr>
                <td colspan="9"><hr/></td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="2" align="right">Shipping Fee (Inclusive GST) :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format($order->shipping_fee, 2) }}</td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="2" align="right">Total Excluding GST :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format($return->amount / 1.06, 2) }}</td>
            </tr>
            <tr class="spanbreaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="2" align="right">GST (6%) :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format(($return->amount / 1.06) * 0.06, 2) }}</td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="2" align="right">Total Including GST :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format($return->amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="9"><hr/></td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="2" align="right">Total GST charged at <br/> Standard Rate (6%) </td>
                <td valign="middle" colspan="1" align="left">: {{ $order->currency }} {{ number_format(($return->amount / 1.06) * 0.06, 2) }}</td>
            </tr>
        </table>
        @if ($issuingCompany->prefix == 'OSHB')
        <div id="returns-container" style="width:200px;float:left;">
            <table>
                <tr>
                    <td>Return Reason</td>
                    <td>
                        <ol>
                            <li>Defect</li>
                            <li>Differenct from website</li>
                            <li>Size is too large</li>
                            <li>Size is too small</li>
                            <li>Disliked</li>
                            <li>Wrong Item</li>
                            <li>Not convinced by the quality</li>
                        </ol>
                    </td>
                </tr>
            </table>
        </div>
        <div id="returns-container" style="width:150px;float:right;">
            <table>
                <tr>
                    <td rowspan="2">Return Reason</td>
                    <td colspan="2">Exchange</td>
                </tr>
                <tr>
                    <td>Qty</td><td>Size/Colour</td>
                </tr>
                <tr>
                    <td><input type="text" size="10"/></td>
                    <td><input type="text" size="5"/></td>
                    <td><input type="text" size="20"/></td>
                </tr>
            </table>
        </div>
        @endif