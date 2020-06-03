<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Form\Preview;

use Nette\Utils\Html;
use WebChemistry\ImageStorage\Entity\PersistentImageInterface;
use WebChemistry\ImageStorage\Filter\FilterInterface;
use WebChemistry\ImageStorage\NetteExtension\Form\ImageUploadControl;

interface ImagePreviewInterface
{

	/**
	 * @return static
	 */
	public function setPlaceholder(?PersistentImageInterface $placeholder);

	/**
	 * @return static
	 */
	public function setFilterObject(?FilterInterface $filter);

	/**
	 * @param mixed[] $options
	 * @return static
	 */
	public function setFilter(string $name, array $options = []);

	/**
	 * @phpstan-return Html<Html|string>|null
	 */
	public function getHtml(ImageUploadControl $input): ?Html;

}
