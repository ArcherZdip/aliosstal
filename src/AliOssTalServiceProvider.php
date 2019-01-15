<?php

namespace ArcherZdip\AliOssTal;

use Illuminate\Support\ServiceProvider;

class AliOssTalServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // 发布配置文件
        if( function_exists('config_path') ) {
            $this->publishes([
                __DIR__ . '/config/config.php' => config_path('aliosstal.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // 合并配置文件
        $this->mergeConfigFrom( __DIR__ . '/config/config.php' , 'aliosstal' );

        $this->app->singleton('aliosstal',function($app){
            $config             = $app->make('config');
            $accessKeyId        = $config->get('aliosstal.access_key_id');
            $accessKeySecret    = $config->get('aliosstal.access_key_secret');
            $isInternal         = $config->get('aliosstal.isinternal');

            return new AliOssTalService(
                $accessKeyId,
                $accessKeySecret,
                $isInternal,
                false
            );
        });
    }


    /**
     * 取得提供者提供的服务
     *
     * @return array
     */
    public function provides()
    {
        return ['aliosstal'];
    }
}
