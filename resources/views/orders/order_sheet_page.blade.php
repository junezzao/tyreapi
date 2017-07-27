<!-- Main content -->
<div class="row">
    <div class="col-xs-12">
      	<div class="box2">
      		<div class="box-header">
          		<h3 class="box-title">@lang('admin/fulfillment.box_header_view_order', ['channel' => $channel->name, 'order' => $order->id])</h3>
        	</div><!-- /.box-header -->
        	<div class="box-body">
        		<div class="col-xs-12">
            		<div class="row">
            			<div class="col-xs-4 col-md-4 pull-right">
		              		<div class="barcode-container" class="pull-right">
								<div>
									{!! DNS1D::getBarcodeSVG($order->id, "C39") !!}
									<br>
									<p class="text-center">{{ $order->id }}</p>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
            			<div class="col-xs-8">
            				<h4>Order Details</h4>
            				<div class="form-group">
	            				<div class="bold col-xs-4"><strong>Order No.: </strong></div>
	            				<div class="col-xs-5"> {{ $order->id }} </div>
	            			</div>
            				<div class="form-group">
	            				<div class="bold col-xs-4"><strong>Third Party Order: </strong></div>
	            				<div class="col-xs-5"> {{ $order->tp_order_code }} <span class="label label-warning">{{ $channel->channel_type->name }}</span></div>
	            			</div>
            				<div class="form-group">
	            				<div class="bold col-xs-4"><strong>Order Date: </strong></div>
	            				<div class="col-xs-5"> {{ $order->created_at }} </div>
	            			</div>
            			</div>
            			<div class="col-xs-4">
            				<div class="consignment-container">
            					<div class="form-group">
            						{!! Form::label('shipping_provider', trans('admin/fulfillment.order_label_shipping_provider'), ['class'=>'control-label full-width']) !!}
            						{!! Form::text('shipping_provider', $order->shipping_provider, ['class' => 'label-input', 'readonly' =>'readonly']) !!}
            					</div>
            					<div class="form-group">
            						{!! Form::label('consignment_no', trans('admin/fulfillment.order_label_consignment_no'), ['class'=>'control-label full-width']) !!}
            						<div class="row">
            							<div class="col-xs-10">
		            						{!! Form::text('consignment_no', (($order->consignment_no == '') ? 'None' : $order->consignment_no), ['class' => 'label-input', 'id' => 'consignment_no', 'placeholder' => trans('admin/fulfillment.order_placeholder_consignment_no'), 'readonly']) !!}
            							</div>
            						</div>
            					</div>
            					<div class="form-group notif-date-container" @if($order->consignment_no == '') style="visibility:hidden" @endif>
            						{!! Form::label('notification_date', trans('admin/fulfillment.order_label_notification_date'), ['class'=>'control-label']) !!}
            						<div style="margin-left:15px" name="notif_date">{{ $order->shipping_notification_date }}</div>
            					</div>
            				</div>
            			</div>
           			</div>
            		<div class="row">
            			<div class="col-xs-4">
							<div class="box2">
							    <div class="box-header">Shipping Details</div>
							    <div class="box-body">
							    	<p class="info-box-text"><span class="bold">Name:</span> {{ $order->shipping_recipient }}</p>
							    	<p class="info-box-text"><span class="bold">Contact Number:</span> {{ $order->shipping_phone }}</p>
							    	<p class="info-box-text"><span class="bold">Address:</span><span class="block address-span"> {{ $order->shipping_street_1 }} <br/> {{ $order->shipping_street_2 }} <br/> {{ $order->shipping_city }} <br/> {{ $order->shipping_postcode}} {{ $order->shipping_state }}<br/>{{ $order->shipping_country }}</span></p>
							    </div>
							</div>
						</div>

						<div class="col-xs-4">
							<div class="box2">
								<div class="box-header">Billing Details</div>
							  	<div class="box-body">
							    	<p class="info-box-text"><span class="bold">Name:</span> {{ (!is_null($member) ? $member->member_name : '') }}</p>
							    	<p class="info-box-text"><span class="bold">Email:</span> {{ (!is_null($member) ? $member->member_email : '')}}</p>
							    	<p class="info-box-text"><span class="bold">Contact Number:</span> {{ (!is_null($member) ? $member->member_mobile : '') }}</p>
							    	<!-- Insert Address -->
							    </div>
							</div>
						</div>
						<div class="col-xs-4">
							<div class="box2">
								<div class="box-header">Payment Details</div>
							  	<div class="box-body">
							  		<p class="info-box-text">
							  			<span class="bold">Payment Type:</span> {{ $order->payment_type }}
							  		</p>
									<p class="info-box-text">
							  			<span class="bold">Promotion Code:</span>
							  		</p>
							  		<p class="info-box-text"><span class="bold">Amount Paid:</span>
							  		{{ $order->currency }} {{ number_format($order->total, 2) }}</p>
							  	</div>
							</div>
						</div>
            		</div>
            		<hr/>
            		<div class="row">
            			<div class="col-xs-12">
            				<table class="table table-striped">
				                <tbody>
				                	<tr>
					                  <th style="width: 10px">#</th>
					                  <th></th>
					                  <th>SKU</th>
					                  <th>Location</th>
					                  <th>Product Name</th>
					                  <th class="text-center">Quantity</th>
					                  <th class="text-center">Listing Price<br/> <span class="small italic"><em>(inclusive GST)</em></span></th>
					                  <th class="text-center">Line Total<br/> <span class="small italic"><em>(inclusive GST)</em></span></th>
					                </tr>
					                @foreach($items as $item)
				                	<tr class="counter">
					                  <td style="width: 10px" class="item-id" data-id="{{ $item->item->id }}"></td>
					                  <td>@if(!empty($item->product->media[0]))<img src="{{ $item->product->media[0]->media->media_url.'_55x79' }}"/>@endif</td>
					                  <td>{{ $item->item->ref->sku->hubwire_sku }}</td>
					                  <td>{{ $item->item->ref->channel_sku_coordinates }}</td>
					                  <td>{{ $item->product->name }}</td>
					                  <td class="text-center">
					                  	{{ $item->item->original_quantity }}
					                  	@if(!empty($item->returns))
						                  	@if($item->returns['Restocked'] > 0)
						                  		<br/><span class="small"><i>(Restocked {{ $item->returns['Restocked'] }})</i></span>
						                  	@endif
						                  	@if($item->returns['InTransit'] > 0)
						                  		<br/><span class="small"><i>(In Transit {{ $item->returns['InTransit'] }})</i></span>
						                  	@endif
						                  	@if($item->returns['Rejected'] > 0)
						                  		<br/><span class="small"><i>(Rejected {{ $item->returns['Rejected'] }})</i></span>
						                  	@endif
					                  	@endif
					                  </td>
					                  <td class="text-center">{{ $order->currency }} {{ ($item->item->sale_price > 0) ? $item->item->sale_price : $item->item->unit_price }}</td>
					                  <td class="text-center">{{ $order->currency }} {{ number_format($item->item->original_quantity * $item->item->sold_price + (($item->item->tax_inclusive) ? 0 : $item->item->tax), 2) }}</td>
					                </tr>
					                @endforeach
					                <tr class="plain-bg-row top-border">
					                	<td colspan="5"></td>
						                <td colspan="2"><span class="pull-right">Sub-total <span class="small italic"><em>(inclusive GST):</em></span></span></td>
						                <td class="text-center">{{ $order->currency }} {{ $order->subtotal }}</td>
					                </tr>
					                <tr class="plain-bg-row">
					                	<td colspan="5"></td>
						                <td colspan="2"><span class="pull-right">Shipping Fee <span class="small italic"><em>(inclusive GST):</em></span></span></td>
						                <td class="text-center">{{ $order->currency }} {{ $order->shipping_fee }}</td>
					                </tr>
					                <tr class="plain-bg-row">
					                	<td colspan="5"></td>
						                <td colspan="2"><span class="pull-right">Total <span class="small italic"><em>(excluding GST):</em></span></span></td>
						                <td class="text-center">{{ $order->currency }} {{ number_format($order->total / 1.06, 2) }}</td>
					                </tr>
					                <tr class="plain-bg-row">
					                	<td colspan="5"></td>
						                <td colspan="2"><span class="pull-right">GST (6%) :</span></td>
						                <td class="text-center">{{ $order->currency }} {{ number_format(($order->total / 1.06) * 0.06, 2) }}</td>
					                </tr>
					                <tr class="plain-bg-row">
					                	<td colspan="5"></td>
						                <td colspan="2"><span class="pull-right">Total <span class="small italic"><em>(inclusive GST):</em></span></span></td>
						                <td class="text-center">{{ $order->currency }} {{ $order->total }}</td>
					                </tr>
					                <tr class="plain-bg-row top-border">
					                	<td colspan="5"></td>
						                <td colspan="2"><span class="pull-right">Total GST charged at <br/> Standard Rate (6%) </span></td>
						                <td class="text-center">{{ $order->currency }} {{ number_format(($order->total / 1.06) * 0.06, 2) }}</td>
					                </tr>
				                </tbody>
            				</table>
            			</div>
            		</div>
            	</div>
        	</div>
        </div>
    </div>
</div>