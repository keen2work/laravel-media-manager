<?php


namespace EMedia\MediaManager\Domain;

use EMedia\MediaManager\Exceptions\FormFieldNotFoundException;
use EMedia\MediaManager\Exceptions\UniqueFilenameGenerationException;
use Illuminate\Support\Facades\Input;

class FileHandler
{

	/**
	 * Upload a file from laravel input and return the saved filename
	 * If a relativeDir is given, it will return the relative path.
	 * Otherwise returns the absolute path
	 *
	 * @param bool $fieldName 			Field name of HTTP input, eg: 'file'
	 * @param bool $absoluteFileDirPath Absolute storage path, eg: '/user/public_html/images/content/'
	 * @param bool $relativeFileDir		Relative storage path, eg: '/images/content/'
	 * @return bool|string
	 */
	public function uploadFile($fieldName = false, $absoluteFileDirPath = false, $relativeFileDir = false)
	{
		if (!$fieldName && !$absoluteFileDirPath) {
			return false;
		}

		// add the last dir separator if it's missing
		$absoluteFileDirPath = rtrim($absoluteFileDirPath, '/') . '/';
		if ($relativeFileDir) {
			$relativeFileDir = rtrim($relativeFileDir, '/') . '/';
		}

		// handle images
		$request = request();
		if (request()->hasFile($fieldName)) {
			$file = Input::file($fieldName);

			$newFileName = $this->getUniqueFileName($absoluteFileDirPath, $file->getClientOriginalName());
			$file->move($absoluteFileDirPath, $newFileName);

			if (!empty($relativeFileDir)) {
				return $relativeFileDir . $newFileName;
			} else {
				return $absoluteFileDirPath . $newFileName;
			}
		} else {
			throw new FormFieldNotFoundException("Field $fieldName is not found with the input.");
		}
		return false;
	}

	/**
	 * Generate a non-conflicting unique file name for the upload
	 *
	 * @param $dirPath
	 * @param $currentFilePath
	 * @return string
	 */
	public function getUniqueFileName($dirPath, $currentFilePath)
	{
		$pathInfo = pathinfo($currentFilePath);
		$newFileName = false;

		for ($i=0; $i < 500; $i++) {
			$newFileName = date('Ymd') . \Illuminate\Support\Str::random(15) . '.' . $pathInfo['extension'];
			if (! file_exists($dirPath . $newFileName)) {
				break;
			}
			if ($i > 499) {
				// failed after almost 500 times
				throw new UniqueFilenameGenerationException();
			}
		}
		return $newFileName;
	}






//	public function downloadFile($fileUrl = false, $imageDirPath = false)
//	{
//		set_time_limit(240);
//		if (!$fileUrl || !$imageDirPath)
//			throw new Exception('A URL and an imageDirPath is required.');
//
//		$newFileName = false;
//
//		$pathInfo = pathinfo($fileUrl);
//		$newFileName = self::getUniqueFileName($imageDirPath, $fileUrl);
//
//		// do we have a new file name?
//		if (!$newFileName) return false;
//
//		$filePath = $imageDirPath . $newFileName;
//		// replace any funny characters in URL
//		$imageUrl = str_replace($pathInfo['filename'], rawurlencode($pathInfo['filename']), $fileUrl);
//		//var_dump($imageUrl); exit;
//
//		$proxyEnabled   = Config::get('settings.proxyEnabled');
//		if ($proxyEnabled)
//		{
//			$proxiesRepo = App::make('\Admin\ProxyRepository');
//			$proxy = $proxiesRepo->getNextProxy();
//
//			// if we have a proxy, send the request through it
//			if ($proxy)
//			{
//				$context = [
//					'http' => [
//						'proxy'             => $proxy->proxy . ':' . $proxy->port,
//						'request_fulluri'   => true
//					]
//				];
//				$context = stream_context_create($context);
//			}
//		}
//
//		try
//		{
//			if (empty($context))
//			{
//				$imageContents 	= file_get_contents($imageUrl);
//			}
//			else
//			{
//				$imageContents = file_get_contents($imageUrl, false, $context);
//			}
//		}
//		catch (Exception $ex)
//		{
//			if ( ! empty($http_response_header) &&
//				strpos($http_response_header[0], '200 OK') === false)
//			{
//				throw new Exception();
//			}
//			else
//			{
//				throw new Exception('Unable to download the file');
//			}
//		}
//
//		$imageResult 	= file_put_contents($filePath, $imageContents);
//		// echo $newFileName;
//		// echo '<br />';
//
//		// return the generated file name or FALSE
//		if ($imageResult)
//		{
//			return $newFileName;
//		}
//		else
//		{
//			return false;
//		}
//	}
}
