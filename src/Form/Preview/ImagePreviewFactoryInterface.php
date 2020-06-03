<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Form\Preview;

interface ImagePreviewFactoryInterface
{

	public function create(): ImagePreviewInterface;

}
