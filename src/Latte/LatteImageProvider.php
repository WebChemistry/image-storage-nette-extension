<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Latte;

use InvalidArgumentException;
use WebChemistry\ImageStorage\Entity\EmptyImage;
use WebChemistry\ImageStorage\Entity\PersistentImage;
use WebChemistry\ImageStorage\Entity\PersistentImageInterface;
use WebChemistry\ImageStorage\LinkGeneratorInterface;

final class LatteImageProvider
{

	private LinkGeneratorInterface $linkGenerator;

	public function __construct(LinkGeneratorInterface $linkGenerator)
	{
		$this->linkGenerator = $linkGenerator;
	}

	/**
	 * @param string|PersistentImageInterface|null $id
	 * @param mixed[] $filter
	 * @param mixed[] $options
	 */
	public function link($id, array $filter, array $options): ?string
	{
		if (is_string($id)) {
			$image = new PersistentImage($id);
		} elseif ($id === null) {
			$image = new EmptyImage();
		} elseif ($id instanceof PersistentImageInterface) {
			$image = $id;
		} else {
			throw new InvalidArgumentException(
				sprintf('First argument must be instance of %s or string or null', PersistentImageInterface::class)
			);
		}

		if (count($filter) > 1) {
			throw new InvalidArgumentException('Cannot use two or more filters.');
		}

		$key = key($filter);
		if ($key !== null) {
			$image = is_int($key) ? $image->withFilter($filter[$key]) : $image->withFilter($key, $filter[$key]);
		}

		return $this->linkGenerator->link($image, $options);
	}

}
