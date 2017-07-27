<!DOCTYPE html>
<html>

@extends('layouts.plain')

@section('title')
    Hubwire 1.0 API Reference
@stop

@section('content')
<div class="container">
    <div class="row">
    	<div class="page-header">
			<h2>Hubwire 1.0 API Reference</h2>
		</div>
		<div class="panel-group" id="api-reference" role="tablist" aria-multiselectable="true">
			<div class="panel panel-default" data-type="api">
				<div class="panel-heading" role="tab" id="apiExplorerHeading">
					<h4 class="panel-title">
					<a role="button" data-toggle="collapse" data-parent="#api-reference" href="#api-explorer" aria-expanded="true" aria-controls="api-explorer">
						API Explorer
					</a>
					</h4>
				</div>
				<div id="api-explorer" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="apiExplorerHeading">
					<div class="panel-body">
						<div class="col-xs-12">
							<form class="form-horizontal">
								<div class="form-group">
									<label for="apiUrl" class="col-sm-2 control-label">API URL:</label>
									<div class="col-sm-10">
										<p class="form-control-static">{{ $apiUrl }}</p>
								    </div>
								</div>
								<div class="form-group">
									<label for="apiUserId" class="col-sm-2 control-label">User ID:</label>
									<div class="col-sm-10">
										<input type="text" class="form-control" name="apiUserId" id="apiUserId" placeholder="User ID">
									</div>
								</div>
								<div class="form-group">
									<label for="apiKey" class="col-sm-2 control-label">API Key:</label>
									<div class="col-sm-10">
										<input type="text" class="form-control" name="apiKey" id="apiKey" placeholder="API Key">
									</div>
								</div>
								<div class="form-group">
									<label for="apiAction" class="col-sm-2 control-label">Action:</label>
									<div class="col-sm-10">
										<select name="apiAction" class="form-control">
											@foreach($actions as $index => $action)
												<option value="{{ $index }}">{{ $action }}</option>
											@endforeach
										</select>
									</div>
								</div>
								<div class="form-group">
									<label for="apiRequestUrl" class="col-sm-2 control-label">Request URL:</label>
									<div class="col-sm-10">
										<input type="text" class="form-control" name="apiRequestUrl" id="apiRequestUrl">
									</div>
								</div>
								<div class="form-group">
									<label for="apiRequestHeader" class="col-sm-2 control-label">Request Header (JSON):</label>
									<div class="col-sm-10">
										<pre name="apiRequestHeader"></pre>
									</div>
								</div>
								<div class="form-group apiPost">
									<label for="apiRequestBody" class="col-sm-2 control-label">Request Body (JSON):</label>
									<div class="col-sm-10">
										<textarea name="apiRequestBody" class="form-control" rows="7"></textarea>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-12">
										<button type="button" data-style="slide-right" id="submit-api-btn" class="btn btn-primary pull-right ladda-button"><span class="ladda-label">Submit</span></button>
									</div>
								</div>
								<div class="form-group">
									<label for="apiResponse">Response</label>
									<pre name="apiResponse"></pre>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
			<div class="panel panel-default" data-type="webhook">
				<div class="panel-heading" role="tab" id="webhookSimHeading">
					<h4 class="panel-title">
					<a role="button" data-toggle="collapse" data-parent="#api-reference" href="#webhook-sim" aria-expanded="true" aria-controls="api-explorer">
						Webhook Simulator
					</a>
					</h4>
				</div>
				<div id="webhook-sim" class="panel-collapse collapse" role="tabpanel" aria-labelledby="webhookSimHeading">
					<div class="panel-body">
						<div class="col-xs-12">
							<form class="form-horizontal">
								<div class="form-group">
									<label for="apiUrl" class="col-sm-2 control-label">API URL:</label>
									<div class="col-sm-10">
										<p class="form-control-static">{{ $apiUrl }}</p>
								    </div>
								</div>
								<div class="form-group">
									<label for="webhookUserId" class="col-sm-2 control-label">User ID:</label>
									<div class="col-sm-10">
										<input type="text" class="form-control" name="webhookUserId" id="webhookUserId" placeholder="User ID">
									</div>
								</div>
								<div class="form-group">
									<label for="webhookAPIKey" class="col-sm-2 control-label">API Key:</label>
									<div class="col-sm-10">
										<input type="text" class="form-control" name="webhookAPIKey" id="webhookAPIKey" placeholder="API Key">
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-12">
										<button type="button" data-style="slide-right" id="load-webhook-url-btn" class="btn btn-primary pull-right ladda-button"><span class="ladda-label">Load Webhook URLs</span></button>
									</div>
								</div>
								<div class="form-group">
									<label for="webhookEvent" class="col-sm-2 control-label">Event:</label>
									<div class="col-sm-10">
										<select name="webhookAction" class="form-control" placeholder="Webhook events">
										</select>
									</div>
								</div>
								<div class="form-group">
									<label for="webhookRefId" class="col-sm-2 control-label">Ref. ID:</label>
									<div class="col-sm-10">
										<input type="text" class="form-control" name="webhookRefId" id="webhookRefId" placeholder="Order ID/Product ID/SKU ID/Media ID">
									</div>
								</div>
								<div class="form-group">
									<label for="webhookEndpointUrl" class="col-sm-2 control-label">Webhook URL:</label>
									<div class="col-sm-10">
										<input type="text" class="form-control" id="webhookEndpointUrl" disabled>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-12">
										<button type="button" data-style="slide-right" id="submit-webhook-btn" class="btn btn-primary pull-right ladda-button"><span class="ladda-label">Submit</span></button>
									</div>
								</div>
								<div class="form-group">
									<label for="webhookResponseData">Webhook Contents</label>
									<pre name="webhookResponseData"></pre>
								</div>
								<div class="form-group">
									<label for="webhookResponseResponse">Your Response</label>
									<pre name="webhookResponseResponse"></pre>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
    </div>
</div>
@stop

@section('footer_scripts')

<link href="{{ asset('css/ladda-themeless.min.css') }}" rel="stylesheet" type="text/css">
<script src="{{ asset('js/spin.min.js') }}"></script>
<script src="{{ asset('js/ladda.min.js') }}"></script>
<script src="{{ asset('js/ladda.jquery.min.js') }}"></script>
<style>
	pre{
		height: 250px;
		overflow-x: scroll;
		width: 100%;
	}
	pre[name=apiRequestHeader]{
		height: 100px;
	}
	pre.error{
		color: red;
	}
	.apiPost{
		display: none;
	}
</style>
<script type="text/javascript">
	var lSubmitApiBtn;
	var lLoadWebhookUrlBtn;
	var lSubmitWebhookBtn;

	function setCookie(cname, cvalue, exdays) {
	    var d = new Date();
	    d.setTime(d.getTime() + (exdays*24*60*60*1000));
	    var expires = "expires="+ d.toUTCString();
	    document.cookie = cname + "=" + cvalue + "; " + expires;
	}

	function getCookie(cname) {
	    var name = cname + "=";
	    var ca = document.cookie.split(';');
	    for(var i = 0; i <ca.length; i++) {
	        var c = ca[i];
	        while (c.charAt(0)==' ') {
	            c = c.substring(1);
	        }
	        if (c.indexOf(name) == 0) {
	            return c.substring(name.length,c.length);
	        }
	    }
	    return "";
	}

	function performAction(){
		var postData = {
			'userId' : $('input[name=apiUserId]').val(),
			'apiKey' : $('input[name=apiKey]').val(),
			'accessToken' : "Bearer " + getCookie('access_token_' + $('input[name=apiUserId]').val() + '_' + $('input[name=apiKey]').val()),
			'action' : $('select[name=apiAction]').val(),
			'requestUrl' : $('input[name=apiRequestUrl]').val(),
			'requestBody' : $('textarea[name=apiRequestBody]').val()
		};

		$.ajax({
            'url': "{{ route('test_api.perform_action') }}",
            'method': 'POST',
            'data': postData,
            'dataType': 'JSON',
            'success': function(response){
                // put response into response textarea
                if(response.error == true){
                	$("pre[name=apiResponse]").html(response.message).addClass('error');
            	}else{
            		$("pre[name=apiResponse]").html(JSON.stringify(response, undefined, 4));
            	}
                lSubmitApiBtn.ladda( 'stop' );
            },
            error: function( jqXHR, textStatus, errorThrown ) {
            	$("pre[name=apiResponse]").html(JSON.stringify(jqXHR.responseText, undefined, 4));
            	lSubmitApiBtn.ladda( 'stop' );
                // console.log(jqXHR.responseText);
            }
        });
	}

	$(document).ready(function(){
		lSubmitApiBtn = $( '#submit-api-btn' ).ladda();
		lLoadWebhookUrlBtn = $( '#load-webhook-url-btn' ).ladda();
		lSubmitWebhookBtn = $( '#submit-webhook-btn' ).ladda();

		var urlActions = [];
		var requireBody = [];
		var sampleRequests = [];

		@foreach($apiRequireBody as $index => $action)
            requireBody[{{ $index }}] = '{{ $action }}';
        @endforeach

        @foreach($sampleRequests as $index => $sampleRequest)
            sampleRequests['{{ $index }}'] = JSON.parse('{!! $sampleRequest !!}');
        @endforeach

        @foreach($actionsUrl as $url => $actionUrl)
            urlActions['{{ $url }}'] = '{{ $actionUrl }}';
        @endforeach

        $("select[name=apiAction]").change(function () {
	        var selectedValue = this.value;
	        if($.inArray(selectedValue, requireBody) >= 0){
	        	$('.apiPost').show();
        		$('textarea[name=apiRequestBody]').val(JSON.stringify(sampleRequests[selectedValue], undefined, 4));
	        }else{
	        	$('.apiPost').hide();
	        }
	        var firstDropVal = $('input[name=apiRequestUrl]').val(urlActions[selectedValue]);
	    });

		$('select[name=apiAction]').trigger('change');

		$('#submit-api-btn').on('click', function(){
			lSubmitApiBtn.ladda( 'start' );
			$("pre[name=apiResponse]").html('').removeClass('error');
			$("pre[name=apiRequestHeader]").html('')
			var accessToken = getCookie('access_token_' + $('input[name=apiUserId]').val() + '_' + $('input[name=apiKey]').val());
			if(accessToken == ''){
				// get access token from HAPI
				var postData = {
					'clientId'		: $('input[name=apiUserId]').val(),
					'clientSecret'	: $('input[name=apiKey]').val(),
				}
				$.ajax({
                    'url': "{{ route('test_api.get_token') }}",
                    'method': 'POST',
                    'data': postData,
                    'dataType': 'json',
                    'success': function(response){
                        // console.log(response);
                        if(response.error == true){
                        	$("pre[name=apiResponse]").html(response.message).addClass('error');
                    	}else{
                    		setCookie('access_token_' + $('input[name=apiUserId]').val() + '_' + $('input[name=apiKey]').val(), response.access_token, 1);
	                        var apiHeader = "\{\"Authorization\": \"Bearer " + response.access_token + "\"}";
	                        apiHeader = JSON.parse(apiHeader);
	                        $("pre[name=apiRequestHeader]").html(JSON.stringify(apiHeader, undefined, 4));
	                        performAction();
                    	}
                    },
	                error: function( jqXHR, textStatus, errorThrown ) {
	                	var response = JSON.parse(jqXHR.responseText);
	                	$("pre[name=apiResponse]").html(JSON.stringify(response, undefined, 4));
	                	lSubmitApiBtn.ladda( 'stop' );
	                    // console.log(jqXHR.responseText);
	                }
                });
			}else{
				// perform action
				// console.log(getCookie('access_token'));
				var apiHeader = "\{\"Authorization\": \"Bearer " + getCookie('access_token_' + $('input[name=apiUserId]').val() + '_' + $('input[name=apiKey]').val()) + "\"}";
				apiHeader = JSON.parse(apiHeader);
                $("pre[name=apiRequestHeader]").html(JSON.stringify(apiHeader, undefined, 4));
                performAction();
			}
		});

		// Webhook
		$('.panel').on('show.bs.collapse', function(){
			// console.log($(this).data('type'));
			var panelType = $(this).data('type');
			var userId = '';
			var apiKey = '';

			if(panelType == 'api'){
				var currentUserId = $('input[name=apiUserId]').val();
				var currentApiKey = $('input[name=apiUserId]').val();
				if(currentUserId == '' && currentApiKey == ''){
					userId = $('input[name=webhookUserId]').val();
					apiKey = $('input[name=webhookAPIKey]').val();
					$('input[name=apiUserId]').val(userId);
					$('input[name=apiUserId]').val(apiKey);
				}
			}else if(panelType == 'webhook'){
				var currentUserId = $('input[name=webhookUserId]').val();
				var currentApiKey = $('input[name=webhookAPIKey]').val();
				if(currentUserId == '' && currentApiKey == ''){
					userId = $('input[name=apiUserId]').val();
					apiKey = $('input[name=apiKey]').val();
					$('input[name=webhookUserId]').val(userId);
					$('input[name=webhookAPIKey]').val(apiKey);
				}
			}
		});

		var eventUrl = [];

		$('#load-webhook-url-btn').on('click', function(){
			lLoadWebhookUrlBtn.ladda('start');
			// get webhook URL from HAPI
			$('input[name=webhookRefId]').closest('.form-group').removeClass('has-error');
			$('input[name=webhookRefId]').val('');
			$("pre[name=webhookResponseData]").removeClass('error').html('');
			$("pre[name=webhookResponseResponse]").removeClass('error').html('');
			var postData = {
				'clientId'		: $('input[name=webhookUserId]').val(),
				'clientSecret'	: $('input[name=webhookAPIKey]').val(),
			}

			$('#webhookEndpointUrl').val('');

			$.ajax({
                'url': "{{ route('test_api.get_webhook_url') }}",
                'method': 'POST',
                'data': postData,
                'dataType': 'json',
                'success': function(response){
                    // console.log(response);
                    $('select[name=webhookAction]').find('option').remove();
                    var webhookUrlOptions = '';
                    eventUrl = [];
                    $.each(response, function( index, webhook ) {
                    	webhookUrlOptions += '<option value="'+webhook.topic+'">'+webhook.topic+'</option>';
                    	eventUrl[webhook.topic] = webhook.address;
                    });
                    $('select[name=webhookAction]').append(webhookUrlOptions);
                    $('select[name=webhookAction]').trigger('change');
                    lLoadWebhookUrlBtn.ladda('stop');
                },
                error: function( jqXHR, textStatus, errorThrown ) {
                	//$("pre[name=apiResponse]").html(jqXHR.responseText);
                    // console.log(jqXHR.responseText);
                    alert(errorThrown);
                    lLoadWebhookUrlBtn.ladda('stop');
                }
            });
		});

		$("select[name=webhookAction]").change(function () {
			var selectedValue = this.value;
			$('#webhookEndpointUrl').val(eventUrl[selectedValue]);
		});

		$('#submit-webhook-btn').on('click', function(){
			$('input[name=webhookRefId]').closest('.form-group').removeClass('has-error');
			if($('input[name=webhookRefId]').val() == ''){
				$('input[name=webhookRefId]').closest('.form-group').addClass('has-error');
				alert('Please enter a ref ID');
			}else{
				lSubmitWebhookBtn.ladda('start');
				var postData = {
					'clientId'		: $('input[name=webhookUserId]').val(),
					'clientSecret'	: $('input[name=webhookAPIKey]').val(),
					'webhookEvent'	: $('select[name=webhookAction]').val(),
					'refId'			: $('input[name=webhookRefId]').val(),
				}
				//test_api.perform_webhook

				$.ajax({
                    'url': "{{ route('test_api.perform_webhook') }}",
                    'method': 'POST',
                    'data': postData,
                    'dataType': 'json',
                    'success': function(response){
                        console.log(response);
                        if(response.error){
                        	$("pre[name=webhookResponseData]").addClass('error').html(response.message);
                        }else{
                        	$("pre[name=webhookResponseData]").removeClass('error').html(JSON.stringify(response.data, undefined, 4));
                        	$("pre[name=webhookResponseResponse]").removeClass('error').html(JSON.stringify(response.response, undefined, 4));
                        }
                        lSubmitWebhookBtn.ladda('stop');
                    },
	                error: function( jqXHR, textStatus, errorThrown ) {
	                	//$("pre[name=apiResponse]").html(jqXHR.responseText);
	                    // console.log(jqXHR.responseText);
	                    alert(errorThrown);
	                    lSubmitWebhookBtn.ladda('stop');
	                }
	            });
			}
		});
	});
</script>
@append