<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // parent::boot(); 
        // Dynamic routing for subdomains
        \Illuminate\Support\Facades\Route::domain('{subdomain}.' . parse_url(config('app.url'), PHP_URL_HOST))
            ->group(function () {
                Route::get('/', function ($subdomain) {
                    $record = \App\Models\Subdomain::where('subdomain_name', $subdomain )->first(); // . '.' . config('app.url')
                    if (!$record) abort(404);
    
                    return response()->file(storage_path("app/subdomains/$subdomain")); // {$record->folder_path}/index.html
                });
            });
    }
}
