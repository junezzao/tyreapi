<html>
	<p>Dear all,</p>

	<p>These are the result from perform quantity check on {{ $channel }}.</p>

	<p>Details as follows:<br><br>
		<table border="1" style="border-style: solid; border-collapse: collapse;">
			<thead>
				<tr style="background-color: lightgrey;">
					<th style="padding: 7px;">Hubwire Chnl SKU</th>
					<th style="padding: 7px;">Quantity in marketplace</th>
					<th style="padding: 7px;">Quantity in Arc</th>
				</tr>
			<thead>
			<tbody>
				@foreach($skuData as $sku)
					<tr style="text-align: center;">
						<td style="padding: 7px;">{{ $sku['hwChnlSkuId'] }}</td>
						<td style="padding: 7px;">{{ $sku['tpQty'] }}</td>
						<td style="padding: 7px;">{{ $sku['hwQty'] }}</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</p>

	This is a system generated email. Please do not reply.</p>
</html>