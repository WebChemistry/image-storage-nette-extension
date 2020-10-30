<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Tracy\Dto;

use Tracy\Helpers;
use WebChemistry\ImageStorage\Entity\ImageInterface;
use WebChemistry\ImageStorage\Entity\PersistentImageInterface;
use WebChemistry\ImageStorage\Event\PersistedImageEvent;
use WebChemistry\ImageStorage\Event\RemovedImageEvent;

final class BarEvent
{

	private string $action;
	private string $result;
	private string $source;
	private string $filter;
	private ?string $entrypoint = null;

	/**
	 * @param PersistedImageEvent|RemovedImageEvent $event
	 */
	public function __construct($event)
	{

		if ($event instanceof RemovedImageEvent) {
			$this->action = '<span style="color:red">remove</span>';
			$this->result = '<span style="color:grey">empty</span>';
			$this->filter = '<span style="color:grey">none</span>';
			$this->source = $event->getSource()->isEmpty() ? '<span style="color:grey">empty</span>' : $event->getSource()->getId();
		} else {
			if ($event->getSource()->getFilter()) {
				$this->action = '<span style="color:blue">filtering</span>';
			} else {
				$this->action = '<span style="color:green">persist</span>';
			}

			$this->result = $event->getResult()->getId();
			$this->filter = $event->getSource()->getFilter() ? $event->getSource()->getFilter()->getName() :
				'<span style="color:grey">none</span>';
			$this->source = $event->getSource()->getId();
		}

		$backtrace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 5);
		foreach ($backtrace as $last) {
			if (isset($last['file']) && $last['file'] !== null && strpos($last['file'], '/vendor/') === false) {
				break;
			}
		}

		if (isset($last['file']) && isset($last['line'])) {
			$this->entrypoint = Helpers::editorLink($last['file'], $last['line']);
		}
	}

	public function getAction(): string
	{
		return $this->action;
	}

	public function getSource(): string
	{
		return $this->source;
	}

	public function getResult(): string
	{
		return $this->result;
	}

	public function getFilter(): string
	{
		return $this->filter;
	}

	public function getEntrypoint(): ?string
	{
		return $this->entrypoint;
	}

}
