<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Form\Entity;

use WebChemistry\ImageStorage\Entity\EmptyImageInterface;
use WebChemistry\ImageStorage\Entity\PersistentImageInterface;
use WebChemistry\ImageStorage\Entity\StorableImageInterface;
use WebChemistry\ImageStorage\ImageStorageInterface;

final class UploadControlEntity
{

	private ?StorableImageInterface $value;

	private ?PersistentImageInterface $default;

	private bool $removeAnyway = false;

	public function __construct(?StorableImageInterface $value = null, ?PersistentImageInterface $default = null)
	{
		$this->value = $value;
		$this->default = $default;
	}

	public function getDefault(): ?PersistentImageInterface
	{
		return $this->default;
	}

	public function getValue(): ?StorableImageInterface
	{
		return $this->value;
	}

	public function withRemoveAnyway(bool $removeAnyway): self
	{
		$clone = clone $this;
		$clone->removeAnyway = $removeAnyway;

		return $clone;
	}

	public function toRemove(): bool
	{
		if (!$this->default) {
			return false;
		}

		return $this->value || $this->removeAnyway;
	}

	public function toPersist(): bool
	{
		return (bool) $this->value;
	}

	public function resolve(ImageStorageInterface $imageStorage): ?PersistentImageInterface
	{
		$value = $this->default;
		if ($this->toRemove()) {
			// @phpstan-ignore-next-line
			$imageStorage->remove($this->default);

			$value = null;
		}

		if ($this->toPersist()) {
			// @phpstan-ignore-next-line
			return $imageStorage->persist($this->value);
		}

		return $value;
	}

	public function withDefault(?PersistentImageInterface $default = null): self
	{
		$clone = clone $this;

		if ($default instanceof EmptyImageInterface) {
			$default = null;
		}

		$clone->default = $default;

		return $clone;
	}

	public function withValue(?StorableImageInterface $value = null): self
	{
		$clone = clone $this;

		if ($value instanceof EmptyImageInterface) {
			$value = null;
		}

		$clone->value = $value;

		return $clone;
	}

}
