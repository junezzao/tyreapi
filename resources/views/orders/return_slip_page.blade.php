<div class="row">
<div class="col-xs-12">
  	<div class="box2">
    	<div class="box-body">
    		@if ($channel->channel_detail->channel_logo!="")
				<div align="center" style="margin-bottom:4px;">
		        	<img alt="Channel Logo" src="{{ $channel->channel_detail->channel_logo }}" height="47">
			    </div>
		    @endif
		    <div style="float:left;">
		        <h2 style="margin:0px 0px 4px 0px; font-size:15pt;"><u>RETURNS SLIP</u></h2>
		    </div>
		    <div style="float:right;">
		        <div><strong>Order Number</strong> : #{{ $order->id }}</div>
		        <div><strong>Order Date</strong> : {{ $order->created_at }}</div>
		    </div>
		    <div style="clear:both;"></div>
		    <p>Thank you for shopping with {{ $channel->name }} and we hope that your experience with us has been a pleasant one!</p>

		    <p>If you are not satisfied with your purchase, you may return the item(s) within 14 days upon receipt of the products. We hope to make the process smooth and easy for you, so please follow the steps below.</p>

		    <p><strong>Step 1</strong>: Please TICK the item(s) and indicate the reason for making the return in the specified area in the table below.</p>

		    <div class="col-lg-12 col-xs-12">
			    <table width="100%">
		            <tr>
		                <th>Tick Here</th>
		                <th>SKU</th>
		                <th>Product Name</th>
		                <th>QTY</th>
		                <th>Reason for Return</th>
		            </tr>
		        
	            	@foreach($items as $item)
	            		<tr>
	            			<td align="center" style="padding-left:5px;"><input type="checkbox"></td>
			                <td>{{ $item->item->ref->sku->hubwire_sku }}</td>
			                <td>{{ $item->product->name }}</td>
			                <td>{{ $item->item->original_quantity }}</td>
			                <td></td>
			            </tr>
			        @endforeach
			    </table>
		    </div>
		    <p><strong>Step 2</strong>: Kindly ship the return to the address below.</p>
		    <p><strong>{!! $channel->address !!}</strong></p>
		    <p><strong>Step 3</strong>: Drop us an email at {{ $channel->channel_detail->support_email }} once you have shipped the items. Please also provide us with the tracking number of the shipment, and any other relevant references.</p>

		    <p>Once we receive the item(s), we will put it through quality control. After we give it the green light, you be refunded with store credits, equivalent to the full amount paid, non-inclusive of any discounts or voucher values.</p>

		    <p>Kindly allow up to 3 business days for the refund to be processed.</p>

		    <p>Should there be any problems with the returned item, we will contact you accordingly. However, if you have any enquiries about your returned item(s), please do not hesitate to send us an e-mail at {{ $channel->channel_detail->support_email }} as we are more than happy to help!</p>
		    <p>We hope that you will continue to enjoy shopping at {{ $channel->name }}. Have a good day!</p>

		    <strong><u>Terms and Conditions</u></strong>
		    <ul style="list-style:disc outside none; font-size:8pt;">
		        <li>{{ $channel->name }} only accepts returns on wrong item(s) sent / defective item(s) / inaccurate sizing.</li>
		        <li>Returned item(s) must be in mint condition - they cannot be washed, worn, altered or damaged.</li>
		        <li>Item(s) are required to be in its original packaging with the tags attached.</li>
		        <li>All returned item(s) are subject to quality control. {{ $channel->name }} has the discretion to evaluate the eligibility of a return if the item(s) do not meet the above mentioned requirements.</li>
		        <li>{{ $channel->name }}  will not be held responsible for any damage or loss caused during the shipment of the returned item(s).</li>
		        <li>Unless it was an error carried out by {{ $channel->name }}, all costs for returns are borne by the customer.</li>
		    </ul>
    	</div>
    </div>
</div>
</div>