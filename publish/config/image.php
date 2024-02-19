<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Image Driver
	|--------------------------------------------------------------------------
	|
	| Intervention Image supports "GD Library" and "Imagick" to process images
	| internally. You may choose one of them according to your PHP
	| configuration. By default PHP's "GD Library" implementation is used.
	|
	| Supported: "gd", "imagick"
	|
	*/

	// Always use the imagick driver for processing. This is a requirement for Oxygen.
	// If imagick is not installed, then install it on YOUR machine.
	// DON'T CHANGE THIS BACK TO GD.

	'driver' => 'imagick'

];
