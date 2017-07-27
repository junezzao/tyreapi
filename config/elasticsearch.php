<?php

use Monolog\Logger;

return array(
    'hosts' => [
                  env('ELASTICSEARCH_HOST','localhost:9200')
               ],
    'logPath' => storage_path() . '/logs/elasticsearch-' . php_sapi_name() . '.log',
    'logLevel' => Logger::INFO
);