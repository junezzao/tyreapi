        <table border="0" cellspacing="1" cellpadding="0" width="100%">
            <tr>
                <td valign="top" colspan="7"><h1>TAX INVOICE {{ $order->tax_invoice_no }}</h1></td>
                <td valign="top" align="right" colspan="3" rowspan="2">
                    <p><img src="{{ asset($issuingCompany->logo_url) }}" class="img-responsive" /></p>
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
                <td valign="top" colspan="6">
                    <p><u>Order Details</u><br/>
                        <p><strong>Tax Invoice:</strong> {{ $order->tax_invoice_no }}</p>
                        <p><strong>Hubwire Order:</strong> #{{ $order->id }}</p>
                        <p><strong>Third Party Order:</strong> {{ (!empty($order->tp_order_code) ? $order->tp_order_code : 'N/A') }} <span class="channel_type">{{ $order->channel_type }}</span></p>
                        <p><strong>Date:</strong> {{ Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format(config('globals.date_format_invoice')) }}</p>
                    </p>
                </td>
            </tr>
            <tr class="breaker">
                <td colspan="10"></td>
            </tr>
            <tr>
                <td valign="top" colspan="4">
                    <p>
                        <u>Shipping Details</u><br/>
                        <strong>Name:</strong> {{ ucwords($order->shipping_recipient) }}
                        <p><strong>Contact Number:</strong> {{ $order->shipping_phone }}</p>
                        <p><strong>Address:</strong> {{ $order->shipping_address }}</p>
                    </p>
                </td>
                <td valign="top" colspan="4">
                    <p>
                        <u>Billing Details</u><br/>
                        <strong>Name:</strong> {{ (!empty($order->member->member_name) ? ucwords($order->member->member_name) : '') }}
                        <p><strong>Email:</strong> {{ (!empty($order->member->member_email) ? $order->member->member_email : '') }}</p>
                        <p><strong>Contact Number:</strong> {{ (!empty($order->member->member_mobile) ? $order->member->member_mobile : '') }}</p>
                    </p>
                </td>
                <td valign="top" colspan="2">
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
                <td valign="middle" colspan="4" class="title">Product Name</td>
                <td valign="middle" class="title">Quantity</td>
                <td valign="middle" class="title">Listing Price<br/><span class="small ita">(Inclusive GST)</span></td>
                <td valign="middle" class="title">Line Total<br/><span class="small ita">(Inclusive GST)</span></td>
            </tr>
            <tr>
                <td colspan="10"><hr/></td>
            </tr>
            @for ($i = 0; $i <= count($order->items) - 1; $i++)
                <tr class="{!! ($i%2 == 0 ? 'even' : 'odd') !!} breaker">
                    <td valign="middle">{{ $i+1 }}</td>
                    <td valign="middle" colspan="2">{{ $order->items[$i]->ref->sku->hubwire_sku }}</td>
                    <td valign="middle" colspan="4">{{ $order->items[$i]->ref->product->name }}</td>
                    <td valign="middle" align="center">{{ $order->items[$i]->original_quantity }}
                    <td valign="middle" align="center">{{ $order->currency }} {{ ($order->items[$i]->sale_price > 0) ? number_format($order->items[$i]->sale_price, 2) : number_format($order->items[$i]->unit_price, 2) }}</td>
                    <td valign="middle" align="center">{{ $order->currency }} {{ number_format($order->items[$i]->sold_price * $order->items[$i]->original_quantity + (($order->items[$i]->tax_inclusive) ? 0 : $order->items[$i]->tax), 2) }}</td>
                </tr>
            @endfor
            <tr>
                <td colspan="10"><hr/></td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="3" align="right">Shipping Fee (Inclusive GST) :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format($order->shipping_fee, 2) }}</td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="3" align="right">Total Excluding GST :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format($order->total / 1.06, 2) }}</td>
            </tr>
            <tr class="spanbreaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="3" align="right">GST (6%) :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format(($order->total / 1.06) * 0.06, 2) }}</td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="3" align="right">Total Including GST :</td>
                <td valign="middle" colspan="1" align="left"> {{ $order->currency }} {{ number_format($order->total, 2) }}</td>
            </tr>
            <tr>
                <td colspan="10"><hr/></td>
            </tr>
            <tr class="breaker">
                <td colspan="6"></td>
                <td valign="middle" colspan="3" align="right">Total GST charged at <br/> Standard Rate (6%) </td>
                <td valign="middle" colspan="1" align="left">: {{ $order->currency }} {{ number_format(($order->total / 1.06) * 0.06, 2) }}</td>
            </tr>
        </table>