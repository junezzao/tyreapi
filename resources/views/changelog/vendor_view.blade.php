<!DOCTYPE html>
<html>
    <head>
        <title>Changelog</title>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" defer></script>
        <link rel="icon" href="{{ asset('images/arc-favicon.png', env('HTTPS',false) ) }}">
    </head>
    <body>
    	<div class="container">
	        <div class="row">
	        	<div class="page-header">
					<h2>Changelog</h2>
				</div>
				<div class="loading-div"></div>
				<div class="list-group"></div>
	        </div>
        </div>
    </body>
    <footer>
    <style>
    	.loading-div {
    		background:url("{{ asset('images/spinner.gif', env('HTTPS',false) ) }}") no-repeat center center;
    		display: block;
    		width:100%;
    		height:66px;
    	}
    	h2, h3, h4 {
    		display: inline-block;
    	}
	    select {
	    	margin-top:30px;
	    }
    </style>
    <script>
	    jQuery(document).ready(function(){
	    	$.ajax({
                type:"GET",
                url: "{{route('changelog.get')}}",
                data: {type: "{{$type}}"},
                beforeSend: function() {
                    $(".loading-div").show();
                },
                success:function(response){
                    console.log(response);
                    if (response.success) {
                    	$.each(response.changelog, function(index, change) {
                            // convert dates to local time
                            var date = convertUTCDateToLocalDate(new Date(change.created_at));
                    		var content = '<div class="list-group-item">' + 
                    						'<h3 class="list-group-item-heading">' + change.title + '</h3>' + 
                    						'<h4 class="list-group-item-heading pull-right created_at">'+date+'</h4>' +
                    						'<div class="list-group-item-text">'+change.content+'</div>' +
                    					  '</div>';

                    		$(".list-group").append(content);
                    	});
                    }
                    else 
                        console.log('An error has occurred.');
                },
                complete: function() {
                	$(".loading-div").hide();
                },
            });

	    	// display changelog according to selected type
		    /*$('#type').on('change', function() {
		    	$(".loading-div").show();
		    	$(".list-group").hide();

		    	var val = $(this).val();
		    	$.each($(".list-group-item"), function() {
		    		if ($(this).hasClass(val) || val=="") {
		    			$(this).removeClass('hide');
		    		}
		    		else
		    			$(this).addClass('hide');
		    	});

		    	$(".list-group").show();
		    	$(".loading-div").hide();
		    });*/

		    // http://stackoverflow.com/a/18330682
		    function convertUTCDateToLocalDate(date) {
			    var newDate = new Date(date.getTime()+date.getTimezoneOffset()*60*1000);

			    var offset = date.getTimezoneOffset() / 60;
			    var hours = date.getHours();

			    newDate.setHours(hours - offset);

			    return newDate.getDate() + '/' + (newDate.getMonth() + 1) + '/' + newDate.getFullYear();
			}
		});
    </script>
    </footer>
</html>
