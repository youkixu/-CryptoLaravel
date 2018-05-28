<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Schema::defaultStringLength(191);

        //custom validation rule: accepts letters hypens and spaces only
        Validator::extend('alpha_spaces', function ($attribute, $value) {
          return preg_match('/^[\pL\s-]+$/u', $value);
        });

        //custom validation rule: accepts letters hypens and spaces only
        Validator::extend('money', function ($attribute, $value) {
          return preg_match('/^\d*(\.\d{1,2})?$/', $value);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
      $this->app->bind('App\Repositories\Portfolio\PortfolioInterface', 'App\Repositories\Portfolio\PortfolioRepository');
      $this->app->bind('App\Repositories\CryptoCurrency\CryptoCurrencyInterface', 'App\Repositories\CryptoCurrency\CryptoCurrencyRepository');
    }
}
