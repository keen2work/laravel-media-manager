<?php


namespace EMedia\MediaManager;

use Illuminate\Support\ServiceProvider;

class MediaManagerServiceProvider extends ServiceProvider
{

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->register(\Intervention\Image\ImageServiceProvider::class);
	}

	public function boot()
	{
		$this->publishes([
			__DIR__ . '/../publish' => base_path(),
		], 'oxygen::auto-publish');
	}
}
