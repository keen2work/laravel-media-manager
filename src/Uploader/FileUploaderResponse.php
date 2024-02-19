<?php


namespace EMedia\MediaManager\Uploader;

use ElegantMedia\PHPToolkit\Path;
use EMedia\MediaManager\Domain\PathResolver;

class FileUploaderResponse
{

	protected $diskName;
	protected $fileName;
	protected $dirPath;
	protected $isSuccessful = false;
	protected $thumbnailPath;
	protected $originalFilename;
	protected $fileSize = 0;

	public function setDiskName($diskName)
	{
		$this->diskName = $diskName;
		return $this;
	}

	public function setFileName($fileName)
	{
		$this->fileName = $fileName;
		return $this;
	}

	public function setThumbnailPath($thumbnailPath)
	{
		$this->thumbnailPath = $thumbnailPath;
		return $this;
	}

	public function setDirPath($dirPath)
	{
		$this->dirPath = $dirPath;
		return $this;
	}

	public function setSuccessful()
	{
		$this->isSuccessful = true;
		return $this;
	}

	public function diskName()
	{
		return $this->diskName;
	}

	public function fileName()
	{
		return $this->fileName;
	}

	public function dirPath()
	{
		return $this->dirPath;
	}

	public function filePath()
	{
		if ($this->isSuccessful() && !empty($this->fileName)) {
			return Path::withEndingSlash($this->dirPath) . $this->fileName;
		}

		return null;
	}

	public function thumbnailPath()
	{
		return $this->thumbnailPath;
	}

	public function publicUrl()
	{
		$filePath = $this->filePath();
		return $this->getPublicUrl($filePath);
	}

	public function publicThumbnailUrl()
	{
		$filePath = $this->thumbnailPath();
		return $this->getPublicUrl($filePath);
	}

	protected function getPublicUrl($path)
	{
		return PathResolver::resolveUrl($this->diskName, $path);
	}

	public function isSuccessful()
	{
		return $this->isSuccessful;
	}

	public function setOriginalFilename($value)
	{
		$this->originalFilename = $value;

		return $this;
	}

	public function getOriginalFilename()
	{
		return $this->originalFilename;
	}

	/**
	 * @return mixed
	 */
	public function getFileSize()
	{
		return $this->fileSize;
	}

	/**
	 * @param mixed $fileSize
	 */
	public function setFileSize($fileSize)
	{
		$this->fileSize = $fileSize;

		return $this;
	}
}
