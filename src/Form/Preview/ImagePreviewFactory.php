<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Form\Preview;

use WebChemistry\ImageStorage\LinkGeneratorInterface;

final class ImagePreviewFactory implements ImagePreviewFactoryInterface
{

	private LinkGeneratorInterface $linkGenerator;

	public function __construct(LinkGeneratorInterface $linkGenerator)
	{
		$this->linkGenerator = $linkGenerator;
	}

	public function create(): ImagePreviewInterface
	{
		return new ImagePreview($this->linkGenerator);
	}

}
