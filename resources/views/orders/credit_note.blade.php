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
    <?php $i=1;?>
    @foreach($items as  $item)
            <div <?php echo ($i<count($items))?'class="page-break"':'';?>>
                @include('orders.credit_note_page',['return'=>$item,'order'=>$order])
            </div>
        <?php $i++;?>
    @endforeach
    <script type="text/javascript">print();</script>
</body>
</html>