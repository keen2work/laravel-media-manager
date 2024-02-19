<?php


namespace EMedia\MediaManager\Uploader;

use ElegantMedia\PHPToolkit\Path;
use EMedia\MediaManager\Domain\ImageHandler;
use EMedia\MediaManager\Exceptions\FormFieldNotFoundException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Mime\MimeTypes;

class FileUploader
{

	protected string|null $saveToDirectory = null;
	protected string $fileFieldName = 'file';
	protected string $diskName = 'default';
	protected string|null $subDirectoryDateFormat = null;
	protected string|null $filePrefixDateFormat = null;
	protected int|null $resizeImageToMaxWidth = null;
	protected int|null $resizeImageToMaxHeight = null;
	protected int|null $withThumbnail = null;
	protected string|null $convertImageToFormat = null;
	protected $convertImageToQuality = null;
	protected $request;
	protected $subDirectories;
	protected int|null $thumbSize = null;

	/* @var UploadedFile $uploadedFile */
	protected UploadedFile|null $uploadedFile = null;
	protected $localFilePath = null;
	protected $keepOriginalFileName = false;

	public function __construct(Request $request = null)
	{
		if ($request) {
			$this->request = $request;
		}
	}

	public function withRequest(Request $request)
	{
		$this->request = $request;
		return $this;
	}

	public function withLocalFile($filePath)
	{
		$this->localFilePath = $filePath;
		return $this;
	}

	public function withUploadedFile(UploadedFile $file)
	{
		$this->uploadedFile = $file;
		return $this;
	}

	public function saveToDir($saveToDirectory)
	{
		$this->saveToDirectory = $saveToDirectory;
		return $this;
	}

	public function fileFieldName($fileFieldName)
	{
		$this->fileFieldName = $fileFieldName;
		return $this;
	}

	public function toDisk($diskName = 'default')
	{
		$this->diskName = $diskName;
		return $this;
	}

	public function intoSubDirectoryDateFormat($dateFormat = 'Ymd')
	{
		$this->subDirectoryDateFormat = $dateFormat;
		return $this;
	}

	public function subDirectories($directoryPath)
	{
		$this->subDirectories = $directoryPath;
		return $this;
	}

	public function prefixFileNameDateFormatAs($dateFormat = null)
	{
		if ($dateFormat) {
			$this->filePrefixDateFormat = $dateFormat;
		}
		return $this;
	}

	public function keepOriginalFileName($keepOriginalFileName = true)
	{
		$this->keepOriginalFileName = $keepOriginalFileName;

		return $this;
	}

	// image functions
	public function resizeImageToMaxWidth($maxWidth = 1200)
	{
		$this->resizeImageToMaxWidth = $maxWidth;
		return $this;
	}

	public function resizeImageToMaxHeight($maxHeight = 800)
	{
		$this->resizeImageToMaxHeight = $maxHeight;

		return $this;
	}

	public function withThumbnail($thumbSize = 100)
	{
		$this->thumbSize = $thumbSize;
		$this->withThumbnail = true;
		return $this;
	}

	/**
	 * @return UploadedFile
	 */
	protected function getFile()
	{
		if ($this->request) {
			return $this->request->file($this->fileFieldName) ?: $this->request->input($this->fileFieldName);
		}

		if ($this->uploadedFile) {
			return $this->uploadedFile;
		}

		throw new InvalidArgumentException("Request or a file has not been set.");
	}

	protected function getLocalFilePath()
	{
		if ($this->request || $this->uploadedFile) {
			$file = $this->getFile();

			return $file->getRealPath();
		}

		if ($this->localFilePath) {
			return $this->localFilePath;
		}

		throw new InvalidArgumentException("File or Request is not set");
	}

	protected function getMimeType()
	{
		if ($this->request || $this->uploadedFile) {
			$file = $this->getFile();
			return $file->getMimeType();
		} elseif ($this->localFilePath) {
			$mimeTypes = new MimeTypes();
			return $mimeTypes->guessMimeType($this->localFilePath);
		}

		throw new InvalidArgumentException("File or Request is not set");
	}

	protected function getFileSize()
	{
		return File::size($this->getLocalFilePath());
	}

	protected function getOriginalFileName()
	{
		if ($this->request || $this->uploadedFile) {
			return $this->getFile()->getClientOriginalName();
		}

		if ($this->localFilePath) {
			$pathInfo = pathinfo($this->localFilePath);
			return $pathInfo['basename'];
		}

		throw new InvalidArgumentException("File or Request is not set");
	}

	protected function isImage()
	{
		$mediaType = ImageHandler::getMediaType($this->getLocalFilePath());

		return $mediaType === 'image';
	}

	/**
	 *
	 * Upload call to process the upload
	 *
	 * @throws FormFieldNotFoundException
	 * @throws FileNotFoundException
	 */
	public function upload()
	{
		// process the object
		$response = $this->uploadFile();

		if ($this->withThumbnail) {
			// generate and upload a thumbnail
			if ($this->isImage()) {
				$thumbnailResponse = $this->uploadFileThumbnail('thumbs', $response);
				$response->setThumbnailPath($thumbnailResponse->filePath());
			}
		}

		return $response;
	}


	public function convertImageToFormat($format, $quality = null)
	{
		if (!in_array($format, ['jpg', 'png', 'gif', 'tif', 'bmp'])) {
			throw new InvalidArgumentException("The requested image conversion format is invalid.");
		}

		$this->convertImageToFormat = $format;
		$this->convertImageToQuality = $quality;

		return $this;
	}

	protected function encodeImageFormat($imagePath, $newFormat, $quality)
	{
		if (!file_exists($imagePath)) {
			throw new FileNotFoundException("Failed to convert the image. File {$imagePath} not found");
		}

		$fileName = date('Ymdhis') . Str::random(15) . '.' . $newFormat;

		$storageDir = Path::withEndingSlash(storage_path('app/images/temp'));
		if (!file_exists($storageDir)) {
			Storage::makeDirectory('images/temp');
		}

		$saveToPath = $storageDir . $fileName;

		// change the format
		$image = Image::make($imagePath)->encode($newFormat, $quality);
		$image->save($saveToPath);

		if (file_exists($saveToPath)) {
			return $saveToPath;
		}

		throw new FileNotFoundException("Unable to generate image or write to file {$saveToPath}");
	}

	protected function resizeImageToMaxSize(
		$imagePath,
		$maxWidth = null,
		$maxHeight = null,
		$overwrite = true,
		$saveToPath = false
	) {
		$image = Image::make($imagePath);
		if ($overwrite) {
			$saveToPath = $imagePath;
		}
		if (!$maxWidth) {
			$maxWidth = $this->resizeImageToMaxWidth;
		}
		if (!$maxHeight) {
			$maxHeight = $this->resizeImageToMaxWidth;
		}

		$newWidth = $newHeight = null;

		if (!$overwrite && $saveToPath == false) {
			// write to a temp storage
			$originalFileName = $this->getOriginalFileName();
			$originalPathInfo = pathinfo($originalFileName);
			$fileName = date('Ymdhis') . Str::random(15) . '.' . $originalPathInfo['extension'];

			$storageDir = Path::withEndingSlash(storage_path('app/images/temp'));

			if (!file_exists($storageDir)) {
				Storage::makeDirectory('images/temp');
			}

			$saveToPath = $storageDir . $fileName;
		}

		$currentWidth = $image->width();
		$currentHeight = $image->height();

		if ($maxWidth !== null && $currentWidth > $maxWidth) {
			if (!$maxWidth > 0) {
				throw new InvalidArgumentException("Image max width must be higher than 0");
			}

			$newWidth = $maxWidth;
		}

		if ($maxHeight !== null && $currentHeight > $maxHeight) {
			if (!$maxHeight > 0) {
				throw new InvalidArgumentException("Image max height must be higher than 0");
			}

			$newHeight = $maxHeight;
		}

		// only resize if at least one of the new values are present
		if ($newWidth > 0 || $newHeight > 0) {
			$image->resize($maxWidth, $maxHeight, function ($constraint) {
				$constraint->aspectRatio();
			});
		}

		$image->save($saveToPath);

		if (file_exists($saveToPath)) {
			return $saveToPath;
		}

		throw new FileNotFoundException("Unable to generate image or write to file {$saveToPath}");
	}


	/**
	 * @return FileUploaderResponse
	 * @throws FileNotFoundException
	 * @throws FormFieldNotFoundException
	 */
	public function uploadFile()
	{
		$diskName = $this->diskName;

		if ($this->request) {
			$request = $this->request;
			$fieldName = $this->fileFieldName;
			$method = $request->method();

			if ($method === 'POST' || strtoupper($request->input('_method')) === 'PUT') {
				if (!$request->hasFile($fieldName)) {
					throw new FormFieldNotFoundException("Field $fieldName is not found with the input.");
				}
			} elseif ($method === 'PUT') {
				if (empty($request->input($fieldName))) {
					throw new FormFieldNotFoundException("Field $fieldName is not found with the input.");
				} else {
					if (!(
						gettype($request->input($fieldName)) === 'object' &&
						get_class($request->input($fieldName)) === 'Illuminate\Http\UploadedFile'
					)) {
						throw new FileException("Field $fieldName is not a file.");
					}
				}
			}
		}

		// add trailing slash
		$dirPath = $this->storageDirPath();

		$mimeType = $this->getMimeType();
		$disk = Storage::disk($diskName);

		// get a unique file name
		if ($this->keepOriginalFileName) {
			$fileName = $this->getOriginalFileName();
		} else {
			$fileName = $this->getUniqueFileName($dirPath, $this->getOriginalFileName(), $disk);
		}

		$workingFilePath = $localFilePath = $this->getLocalFilePath();

		// resize before uploading if required
		$filesToDelete = [];
		// $resizedStreamPath = null;
		$stream = null;
		if ($this->resizeImageToMaxWidth || $this->resizeImageToMaxHeight) {
			$workingFilePath = $this->resizeImageToMaxSize(
				$localFilePath,
				$this->resizeImageToMaxWidth,
				$this->resizeImageToMaxHeight,
				false
			);
			// because we're not overriding the original local file
			// we should delete this file later
			$filesToDelete[] = $workingFilePath;
			// $stream = fopen($resizedStreamPath, 'r+b');
		}

		// should we change the format?
		if (!empty($this->convertImageToFormat)) {
			$workingFilePath = $this->encodeImageFormat(
				$workingFilePath,
				$this->convertImageToFormat,
				$this->convertImageToQuality
			);

			// let's delete the original, because it will be renamed
			$filesToDelete[] = $fileName;
			$filesToDelete[] = $workingFilePath;

			// because we're changing the format, replace the file extension
			$fileNameSegments = pathinfo($fileName);
			$workingFileSegments = pathinfo($workingFilePath);
			if ($fileNameSegments['dirname'] === '.') {
				$fileName = '';
			} else {
				$fileName = $fileNameSegments['dirname'] . '/';
			}
			$fileName .= $fileNameSegments['filename'] . '.' . $workingFileSegments['extension'];

			// if we changed the image format, we need to change the MIME type
			$mimeTypes = new MimeTypes();
			$mimeType = $mimeTypes->guessMimeType($workingFilePath);
		}

		$stream = fopen($workingFilePath, 'r+b');

		$filePath = $dirPath . $fileName;

		$result = false;
		try {
			$disk->getDriver()->writeStream($filePath, $stream, ['mimetype' => $mimeType]);
			if ($disk->exists($filePath)) {
				$result = true;
			}
		} catch (FilesystemException | UnableToWriteFile $exception) {
			// $result = false;
			throw $exception;
		}

		if (is_resource($stream)) {
			fclose($stream);
		}

		// remove the temp files
		foreach ($filesToDelete as $tempFilePath) {
			if (file_exists($tempFilePath)) {
				unlink($tempFilePath);
			}
		}

		// if we have a resized temp image, delete it
//		if (($this->resizeImageToMaxWidth || $this->resizeImageToMaxHeight) && isset($streamPath)) {
//			 unlink($streamPath);
//		}

		$response = new FileUploaderResponse();

		if ($result) {
			$response->setSuccessful()
				->setDiskName($diskName)
				->setDirPath($dirPath)
				->setOriginalFileName($this->getOriginalFileName())
				->setFileName($fileName)
				->setFileSize($this->getFileSize());
		}

		return $response;
	}

	protected function uploadFileThumbnail($subDirectoryPath, FileUploaderResponse $response)
	{
		$dirPath = $response->dirPath() . Path::withEndingSlash($subDirectoryPath);
		$uploadFilePath = $dirPath . $response->fileName();

		$imagePath = $this->resizeImageToMaxSize(
			$this->getLocalFilePath(),
			$this->thumbSize,
			$this->thumbSize,
			false
		);

		$mimeType = $this->getMimeType();
		$disk = Storage::disk($this->diskName);

		$stream = fopen($imagePath, 'r+b');

		$result = false;
		try {
			$disk->getDriver()->writeStream($uploadFilePath, $stream, ['mimetype' => $mimeType]);
			if ($disk->exists($uploadFilePath)) {
				$result = true;
			}
		} catch (FilesystemException | UnableToWriteFile $exception) {
			// $result = false;
		}

		if (is_resource($stream)) {
			fclose($stream);
		}

		// delete the local copy
		unlink($imagePath);

		$thumbResponse = new FileUploaderResponse();

		if ($result) {
			$thumbResponse->setSuccessful()
				->setDiskName($this->diskName)
				->setDirPath($dirPath)
				->setFileName($response->fileName());
		}

		return $thumbResponse;
	}

	public function getUniqueFileName(
		$dirPath,
		$currentFilePath,
		FilesystemAdapter $disk = null
	) {
		$pathInfo = pathinfo($currentFilePath);
		$newFileName = false;

		// add trailing slash
		$dirPath = Path::withEndingSlash($dirPath);


		$filePrefix = ($this->filePrefixDateFormat)? date($this->filePrefixDateFormat): '';

		for ($i=0; $i < 50; $i++) {
			$newFileName = $filePrefix . Str::random(15) . '.' . $pathInfo['extension'];
			$newFilePath = $dirPath . $newFileName;

			if (!$disk->exists($newFilePath)) {
				break;
			}

			if ($i > 49) {
				// if failed to generate after n times, do a has + timestamp
				$newFileName = $filePrefix . date('His') . md5_file($pathInfo['filename']).'.'.$pathInfo['extension'];
			}
		}

		return $newFileName;
	}

	protected function storageDirPath()
	{
		$subFolderDateFormat = $this->subDirectoryDateFormat;

		// add trailing slash
		$dirPath = Path::withEndingSlash($this->saveToDirectory);

		// dated subdirectories
		if ($subFolderDateFormat && $subFolderDateFormat != '') {
			$dirPath .= date($subFolderDateFormat) . '/';
		}

		// other subdirectories
		if ($this->subDirectories) {
			$subDirectoryPath = Path::withEndingSlash($this->subDirectories);
			$dirPath .= $subDirectoryPath;
		}

		return $dirPath;
	}
}
