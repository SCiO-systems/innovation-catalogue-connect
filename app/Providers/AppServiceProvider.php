<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //Calls to the cacheapi/retrievevalue endpoint at scio.services to retrieve the clarisa vocabularies
        Http::macro('redisFetch',function (){
           $response =  Http::post(env('SCIO_REDIS_API','').'/cacheapi/retrievevalue', [
               "key" => "clarisa_vocabularies",             //key needed for redis
           ]);
            return json_decode($response['response']);      //transform the data to json
        });
    }
}
