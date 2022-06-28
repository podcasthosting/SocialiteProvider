# SocialiteProvider
Laravel Socialite Provider to log in to podcaster service ([www.podcaster.de](https://www.podcaster.de))

Add this app/Providers/EventServiceProvider.php

    private function bootPodcasterSocialite()
    {
        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');
        $socialite->extend(
            'podcaster',
            function ($app) use ($socialite) {
                $config = $app['config']['services.podcaster'];
                return $socialite->buildProvider(\podcasthosting\podcaster\socialiteprovider\Provider::class, $config);
            }
        );
    }

and call it from boot() method.


    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootPodcasterSocialite();

        parent::boot();
    }
