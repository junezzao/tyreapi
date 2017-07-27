<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style type="text/css">
        body { font-family: "Open Sans", Arial, sans-serif; }
        table { font-size:12px; }
            th { color: white; -webkit-print-color-adjust: exact; }
        tr.breaker td { padding:15px 7px; }
        tr td.title { padding: 5px 7px; }
        tr.spanbreaker td { padding: 5px 7px; }
        .title { align:center; font-size:13px; font-weight:bold; }
        .odd { background-color: #ececec; }
        .even { background-color: #f8f8f8; }
        hr { margin: 0 auto; }
        .channel_type { border: 1px solid #999; border-radius: 3px; background-color: #f8f8f8; padding: 5px; font-size: 11px; }
        .small { font-size: 9px; }
        .ita { font-style: italic; }
        .page-break{page-break-after: always;}
    </style>
    <script type="text/javascript">
        function printpage() {
            window.print();
        }
    </script>
</head>
<!--body onload="printpage();"-->
<body>
    <div class="page-break">
        <table border="0" cellspacing="1" cellpadding="0" width="100%">
            <tr>
                <td valign="top" colspan="8"><h1>INVOICE {{ $order->tax_invoice_no }}</h1></td>
                <td valign="top" align="right" colspan="4" rowspan="2">
                    <p><img src="{{ asset($issuingCompany->logo_url) }}" class="img-responsive" /></p>
                    <p class="small"><strong>{{ $issuingCompany->name }}</strong>
                        <br>{!! $issuingCompany->address !!}
                    @if(!empty($issuingCompany->extra))
                        @foreach($issuingCompany->extra as $k => $v)
                        <br>{{$k}} : {{$v}}
                        @endforeach
                    @endif
                </td>
            </tr>
            <tr>
                <td valign="top" colspan="12">
                    <p><u>Order Details</u><br/>
                        <p><strong>Invoice:</strong> {{ $order->tax_invoice_no }}</p>
                        <p><strong>Hubwire Order:</strong> #{{ $order->id }}</p>
                        <p><strong>Third Party Order:</strong> {{ (!empty($order->tp_order_code) ? $order->tp_order_code : 'N/A') }} <span class="channel_type">{{ $order->channel_type }}</span></p>
                        <p><strong>Date:</strong> {{ Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format(config('globals.date_format_invoice')) }}</p>
                    </p>
                </td>
            </tr>
            <tr class="breaker">
                <td colspan="12"></td>
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
                <td valign="top" colspan="4">
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
                <td valign="middle" colspan="3" class="title">SKU</td>
                <td valign="middle" colspan="5" class="title">Product Name</td>
                <td valign="middle" class="title">Quantity</td>
                <td valign="middle" class="title">Listing Price<br/></td>
                <td valign="middle" class="title">Line Total<br/></td>
            </tr>
            <tr>
                <td colspan="12"><hr/></td>
            </tr>
            @for ($i = 0; $i <= count($order->items) - 1; $i++)
                <tr class="{!! ($i%2 == 0 ? 'even' : 'odd') !!} breaker">
                    <td valign="middle">{{ $i+1 }}</td>
                    <td valign="middle" colspan="3">{{ $order->items[$i]->ref->sku->hubwire_sku }}</td>
                    <td valign="middle" colspan="5">{{ $order->items[$i]->ref->product->name }}</td>
                    <td valign="middle" align="center">{{ $order->items[$i]->original_quantity }} 
                    <td valign="middle" align="center">{{ $order->currency }} {{ ($order->items[$i]->sale_price > 0) ? number_format($order->items[$i]->sale_price, 2) : number_format($order->items[$i]->unit_price, 2) }}</td>
                    <td valign="middle" align="center">{{ $order->currency }} {{ number_format($order->items[$i]->sold_price * $order->items[$i]->original_quantity, 2) }}</td>
                </tr>
            @endfor
            <tr>
                <td colspan="12"><hr/></td>
            </tr>
            <tr class="breaker">
                <td colspan="8"></td>
                <td valign="middle" colspan="2" align="right">Shipping Fee :</td>
                <td valign="middle" colspan="2" align="left"> {{ $order->currency }} {{ number_format($order->shipping_fee, 2) }}</td>
            </tr>
            <tr class="breaker">
                <td colspan="8"></td>
                <td valign="middle" colspan="2" align="right">Total :</td>
                <td valign="middle" colspan="2" align="left"> {{ $order->currency }} {{ number_format($order->total, 2) }}</td>
            </tr>
            <tr>
                <td colspan="12"><hr/></td>
            </tr>
        </table>
    </div>
    <div>
        <table border="0" cellspacing="1" cellpadding="0" width="100%">
            <tr>
                <td valign="top" colspan="8"><h1>INVOICE {{ $order->tax_invoice_no }}</h1></td>
                <td valign="top" align="right" colspan="4" rowspan="2">
                    <p><img src="{{ asset($issuingCompany->logo_url) }}" class="img-responsive" /></p>
                    <p class="small"><strong>{{ $issuingCompany->name }}</strong>
                        <br>{!! $issuingCompany->address !!}
                    @if(!empty($issuingCompany->extra))
                        @foreach($issuingCompany->extra as $k => $v)
                        <br>{{$k}} : {{$v}}
                        @endforeach
                    @endif
                </td>
            </tr>
            <tr>
                <td valign="top" colspan="12">
                    <p><u>Order Details</u><br/>
                        <p><strong>Invoice:</strong> {{ $order->tax_invoice_no }}</p>
                        <p><strong>Hubwire Order:</strong> #{{ $order->id }}</p>
                        <p><strong>Third Party Order:</strong> {{ (!empty($order->tp_order_code) ? $order->tp_order_code : 'N/A') }} <span class="channel_type">{{ $order->channel_type }}</span></p>
                        <p><strong>Date:</strong> {{ Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format(config('globals.date_format_invoice')) }}</p>
                    </p>
                </td>
            </tr>
            <tr class="breaker">
                <td colspan="12"></td>
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
                <td valign="top" colspan="4">
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
                <td valign="middle" colspan="3" class="title">SKU</td>
                <td valign="middle" colspan="5" class="title">Product Name</td>
                <td valign="middle" class="title">Quantity</td>
                <td valign="middle" class="title">Listing Price<br/></td>
                <td valign="middle" class="title">Line Total<br/></td>
            </tr>
            <tr>
                <td colspan="12"><hr/></td>
            </tr>
            @for ($i = 0; $i <= count($order->items) - 1; $i++)
                <tr class="{!! ($i%2 == 0 ? 'even' : 'odd') !!} breaker">
                    <td valign="middle">{{ $i+1 }}</td>
                    <td valign="middle" colspan="3">{{ $order->items[$i]->ref->sku->hubwire_sku }}</td>
                    <td valign="middle" colspan="5">{{ $order->items[$i]->ref->product->name }}</td>
                    <td valign="middle" align="center">{{ $order->items[$i]->original_quantity }} 
                    <td valign="middle" align="center">{{ $order->currency }} {{ ($order->items[$i]->sale_price > 0) ? number_format($order->items[$i]->sale_price, 2) : number_format($order->items[$i]->unit_price, 2) }}</td>
                    <td valign="middle" align="center">{{ $order->currency }} {{ number_format($order->items[$i]->sold_price * $order->items[$i]->original_quantity, 2) }}</td>
                </tr>
            @endfor
            <tr>
                <td colspan="12"><hr/></td>
            </tr>
            <tr class="breaker">
                <td colspan="8"></td>
                <td valign="middle" colspan="2" align="right">Shipping Fee :</td>
                <td valign="middle" colspan="2" align="left"> {{ $order->currency }} {{ number_format($order->shipping_fee, 2) }}</td>
            </tr>
            <tr class="breaker">
                <td colspan="8"></td>
                <td valign="middle" colspan="2" align="right">Total :</td>
                <td valign="middle" colspan="2" align="left"> {{ $order->currency }} {{ number_format($order->total, 2) }}</td>
            </tr>
        </table>
    </div>
    <script type="text/javascript">print();</script>
</body>
</html>