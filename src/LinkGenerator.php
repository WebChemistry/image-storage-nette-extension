<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension;

use Nette\Http\IRequest;
use WebChemistry\ImageStorage\Entity\PersistentImageInterface;
use WebChemistry\ImageStorage\LinkGeneratorInterface;

final class LinkGenerator implements LinkGeneratorInterface
{

	private LinkGeneratorInterface $decorated;

	private string $baseUrl;

	public function __construct(LinkGeneratorInterface $decorated, IRequest $request)
	{
		$this->decorated = $decorated;
		$this->baseUrl = rtrim($request->getUrl()->getBaseUrl(), '/');
	}

	/**
	 * @inheritDoc
	 */
	public function link(?PersistentImageInterface $image, array $options = []): ?string
	{
		$path = $this->decorated->link($image, $options);
		if (!$path) {
			return null;
		}

		return $this->baseUrl . $path;
	}

}
