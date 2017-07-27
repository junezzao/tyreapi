<?php

$domainPrefix = (env('APP_ENV') == 'production') ? '' : env('APP_ENV') . '-';

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    */

    'name' => env('APP_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => env('APP_DEBUG', true),
    'env'   => env('APP_ENV', 'production'), //READ --> https://laravel.com/docs/5.2/upgrade#upgrade-5.2.0 

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://' . $domainPrefix . 'api.hubwire.com'),
    //'api_url' => env('APP_API_URL', 'http://' . $domainPrefix . 'api.hubwire.com'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY', 'SomeRandomString'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log settings for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Settings: "single", "daily", "syslog", "errorlog"
    |
    */

    'log' => env('APP_LOG', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Foundation\Providers\ArtisanServiceProvider::class, //--Removed to upgrade to L5.2
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        //Illuminate\Routing\ControllerServiceProvider::class,// --Removed to upgarde to L5.2
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        Collective\Html\HtmlServiceProvider::class,
        // Collective\Bus\BusServiceProvider::class,
        Laracasts\Flash\FlashServiceProvider::class,
        
        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\ChannelServiceProvider::class,
        App\Providers\PartnerServiceProvider::class,
        App\Providers\OAuthServiceProvider::class,
        App\Providers\StockTransferServiceProvider::class,
        App\Providers\SalesServiceProvider::class,
        App\Providers\RepositoryServiceProvider::class,
        App\Providers\HelperServiceProvider::class,
        
        /*
            Third Party Service Providers
        */
        LucaDegasperi\OAuth2Server\Storage\FluentStorageServiceProvider::class,
        LucaDegasperi\OAuth2Server\OAuth2ServerServiceProvider::class,
        Bican\Roles\RolesServiceProvider::class,
        Spatie\Activitylog\ActivitylogServiceProvider::class,
        Caffeinated\Modules\ModulesServiceProvider::class,
        Maatwebsite\Excel\ExcelServiceProvider::class,
        Shift31\LaravelElasticsearch\ElasticsearchServiceProvider::class,
        Barryvdh\DomPDF\ServiceProvider::class,
        Milon\Barcode\BarcodeServiceProvider::class,
        Vinkla\Pusher\PusherServiceProvider::class,
        Intervention\Image\ImageServiceProvider::class,
        Aws\Laravel\AwsServiceProvider::class,

        //For testing purposes only
        Rap2hpoutre\LaravelLogViewer\LaravelLogViewerServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => [

        'App'       => Illuminate\Support\Facades\App::class,
        'Artisan'   => Illuminate\Support\Facades\Artisan::class,
        'Auth'      => Illuminate\Support\Facades\Auth::class,
        'Blade'     => Illuminate\Support\Facades\Blade::class,
        'Bus'       => Illuminate\Support\Facades\Bus::class,
        'Cache'     => Illuminate\Support\Facades\Cache::class,
        'Config'    => Illuminate\Support\Facades\Config::class,
        'Cookie'    => Illuminate\Support\Facades\Cookie::class,
        'Crypt'     => Illuminate\Support\Facades\Crypt::class,
        'DB'        => Illuminate\Support\Facades\DB::class,
        'Eloquent'  => Illuminate\Database\Eloquent\Model::class,
        'Event'     => Illuminate\Support\Facades\Event::class,
        'File'      => Illuminate\Support\Facades\File::class,
        'Gate'      => Illuminate\Support\Facades\Gate::class,
        'Hash'      => Illuminate\Support\Facades\Hash::class,
        'Input'     => Illuminate\Support\Facades\Input::class,
        'Lang'      => Illuminate\Support\Facades\Lang::class,
        'Log'       => Illuminate\Support\Facades\Log::class,
        'Mail'      => Illuminate\Support\Facades\Mail::class,
        'Password'  => Illuminate\Support\Facades\Password::class,
        'Queue'     => Illuminate\Support\Facades\Queue::class,
        'Redirect'  => Illuminate\Support\Facades\Redirect::class,
        'Redis'     => Illuminate\Support\Facades\Redis::class,
        'Request'   => Illuminate\Support\Facades\Request::class,
        'Response'  => Illuminate\Support\Facades\Response::class,
        'Route'     => Illuminate\Support\Facades\Route::class,
        'Schema'    => Illuminate\Support\Facades\Schema::class,
        'Session'   => Illuminate\Support\Facades\Session::class,
        'Storage'   => Illuminate\Support\Facades\Storage::class,
        'URL'       => Illuminate\Support\Facades\URL::class,
        'Validator' => Illuminate\Support\Facades\Validator::class,
        'View'      => Illuminate\Support\Facades\View::class,
        'Authorizer' => LucaDegasperi\OAuth2Server\Facades\Authorizer::class,
        'Activity'  => Spatie\Activitylog\ActivitylogFacade::class,
        'Module'    => Caffeinated\Modules\Facades\Module::class,
        'Excel'     => Maatwebsite\Excel\Facades\Excel::class,
        'Helper'    => App\Helpers\Helper::class,
        'PDF'       => Barryvdh\DomPDF\Facade::class,
        'DNS1D'     => Milon\Barcode\Facades\DNS1DFacade::class,
        'DNS2D'     => Milon\Barcode\Facades\DNS2DFacade::class,
        'Form'      => Collective\Html\FormFacade::class,
        'Html'      => Collective\Html\HtmlFacade::class,
        'Image'     => Intervention\Image\Facades\Image::class,
        'AWS'       => Aws\Laravel\AwsFacade::class,


        /*
         * Polymorphic Relations
         */
        'ChannelSKU'        => App\Models\Admin\ChannelSKU::class,
        'PromotionCode'     => App\Models\Admin\PromotionCode::class,
        'StoreCredit'       => App\Models\Admin\StoreCredit::class,
        'ItemDiscount'      => App\Models\Admin\ItemDiscount::class,
        'StatusLog'         => App\Models\Admin\OrderStatusLog::class,
        'Order'             => App\Models\Admin\Order::class,
        'Channel'           => App\Models\Admin\Channel::class,
        'Merchant'          => App\Models\Admin\Merchant::class,
        'StockCorrection'   => App\Models\Admin\StockCorrection::class,
        'Unknown'           => App\Models\Admin\Unknown::class,
        'RejectLog'         => App\Models\Admin\RejectLog::class,
        'Purchase'          => App\Models\Admin\Purchase::class,
        'ReturnLog'         => App\Models\Admin\ReturnLog::class,
        'DeliveryOrder'     => App\Models\Admin\DeliveryOrder::class,        
        'OrderItem'         => App\Models\Admin\OrderItem::class,
        'DeliveryOrderItem' => App\Models\Admin\DeliveryOrderItem::class,
        /*
         * Third Party Controller
         */
        'ElevenStreetController'            => App\Modules\ThirdParty\Http\Controllers\ElevenStreetController::class,
        'LazadaController'                  => App\Modules\ThirdParty\Http\Controllers\LazadaController::class,
        'LazadaScController'                => App\Modules\ThirdParty\Http\Controllers\LazadaScController::class,
        'ZaloraController'                  => App\Modules\ThirdParty\Http\Controllers\ZaloraController::class,
        'LelongController'                  => App\Modules\ThirdParty\Http\Controllers\LelongController::class,
        'ShopifyController'                 => App\Modules\ThirdParty\Http\Controllers\ShopifyController::class,
        'NonIntegratedController'           => App\Modules\ThirdParty\Http\Controllers\NonIntegratedController::class,
        'StorefrontVendorController'        => App\Modules\ThirdParty\Http\Controllers\StorefrontVendorController::class,
        'BearInBagController'               => App\Modules\ThirdParty\Http\Controllers\BearInBagController::class,
        'RubberNeckController'              => App\Modules\ThirdParty\Http\Controllers\RubberNeckController::class,
        'AmaxMallController'                => App\Modules\ThirdParty\Http\Controllers\AmaxMallController::class,
        'ShopeeController'                  => App\Modules\ThirdParty\Http\Controllers\ShopeeController::class,
    ],

];
