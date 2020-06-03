<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Form\Remove;

use Nette\Utils\Html;
use WebChemistry\ImageStorage\NetteExtension\Form\ImageUploadControl;

interface ImageRemoveInterface
{

	public function getHttpData(ImageUploadControl $input): bool;

	/**
	 * @phpstan-return Html<Html|string>|null
	 */
	public function getHtml(ImageUploadControl $input): ?Html;

}
