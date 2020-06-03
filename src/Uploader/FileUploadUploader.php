<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Uploader;

use Nette\Http\FileUpload;
use WebChemistry\ImageStorage\NetteExtension\Exceptions\InvalidArgumentException;
use WebChemistry\ImageStorage\NetteExtension\Exceptions\InvalidFileUploadException;
use WebChemistry\ImageStorage\Uploader\UploaderInterface;

final class FileUploadUploader implements UploaderInterface
{

	private FileUpload $fileUpload;

	public function __construct(FileUpload $fileUpload)
	{
		if (!$fileUpload->isOk()) {
			throw new InvalidArgumentException('Passed file is not ok');
		}

		if (!$fileUpload->isImage()) {
			throw new InvalidArgumentException('Passed file is not an image');
		}

		$this->fileUpload = $fileUpload;
	}

	public function getContent(): string
	{
		$content = $this->fileUpload->getContents();

		if ($content === null) {
			throw new InvalidFileUploadException(
				sprintf('Cannot get content from %s', $this->fileUpload->getSanitizedName())
			);
		}

		return $content;
	}

}
