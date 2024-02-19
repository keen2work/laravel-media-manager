<?php

namespace EMedia\MediaManager\Domain;

use EMedia\MediaManager\Exceptions\FailedToResolvePathException;
use ElegantMedia\PHPToolkit\Path;
use Illuminate\Support\Facades\Storage;

class PathResolver
{

	/**
	 *
	 * Resolve disk path from the disk
	 *
	 * @param $diskName
	 * @return string
	 * @throws FailedToResolvePathException
	 */
	public static function resolveDiskPath($diskName): string
	{
		$driverName = config('filesystems.disks.' . $diskName . '.driver');

		if ($driverName !== 'local') {
			throw new \InvalidArgumentException(
				"The given disk name `{$diskName}` is invalid. A `local` disk is required."
			);
		}

		$root = config("filesystems.disks.{$diskName}.root");
		if (!empty($root)) {
			return Path::withoutEndingSlash($root);
		}

		throw new FailedToResolvePathException("Only paths from a local disk can be resolved.");
	}

	/**
	 *
	 * Resolve file path from the disk
	 *
	 * @param $diskName
	 * @param $filePath
	 *
	 * @return string
	 * @throws FailedToResolvePathException
	 */
	public static function resolvePath($diskName, $filePath): string
	{
		$driverName = config('filesystems.disks.' . $diskName . '.driver');

		if ($driverName !== 'local') {
			throw new FailedToResolvePathException('Only `local` disk paths can be resolved.');
		} else {
			$diskPath = self::resolveDiskPath($diskName);

			return $diskPath . Path::withStartingSlash($filePath);
		}
	}

	/**
	 *
	 * Resolve a URL to a filepath on a disk
	 *
	 * @param $diskName
	 * @param $filePath
	 *
	 * @return bool|mixed|string
	 */
	public static function resolveUrl($diskName, $filePath)
	{
		$path = Path::withoutStartingSlash($filePath);

		if (!empty($path)) {
			$disk = Storage::disk($diskName);

			return $disk->url($path);
		}

		return false;
	}


	/**
	 *
	 * Get an pre-signed URL for temporary access
	 *
	 * @param        $disk
	 * @param        $imagePath
	 * @param string $expiry
	 *
	 * @return string
	 */
	public function getPresignedUrl($disk, $imagePath, $expiry = '+10 minutes')
	{
		$s3 = \Storage::disk($disk);
		$adapter = $s3->getDriver()->getAdapter();

		if ($adapter instanceof \League\Flysystem\AwsS3v3\AwsS3Adapter) {
			$client  = $adapter->getClient();

			$command = $client->getCommand('GetObject', [
				'Bucket' => config('filesystems.disks.' . $disk . '.bucket'),
				'Key'    => $imagePath,
			]);

			$request = $client->createPresignedRequest($command, $expiry);

			return (string) $request->getUri();
		}

		return $imagePath;
	}

	/**
	 *
	 * Add a file name suffix to a given path
	 *
	 * @param $filePath
	 * @param $suffix
	 * @return string
	 */
	public static function addNameSuffix($filePath, $suffix)
	{
		$pathinfo = pathinfo($filePath);

		$output = [];
		if ($pathinfo['dirname'] !== '.') {
			$output[] = $pathinfo['dirname'];
			$output[] = '/';
		}

		$output[] = $pathinfo['filename'] . '_';
		$output[] = $suffix;
		$output[] = '.' . $pathinfo['extension'];

		return implode('', $output);
	}
}
