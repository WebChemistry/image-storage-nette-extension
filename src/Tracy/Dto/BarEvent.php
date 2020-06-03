<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\Tracy\Dto;

use Tracy\Helpers;
use WebChemistry\ImageStorage\Entity\ImageInterface;
use WebChemistry\ImageStorage\Event\PersistedImageEvent;
use WebChemistry\ImageStorage\Event\RemovedImageEvent;

final class BarEvent
{

	private string $action;

	private string $result;

	private string $source;

	private string $filter;

	private string $entrypoint;

	/**
	 * @param PersistedImageEvent|RemovedImageEvent $event
	 */
	public function __construct($event)
	{
		if ($event instanceof RemovedImageEvent) {
			$this->action = '<span style="color:red">remove</span>';
			$this->result = '<span style="color:grey">empty</span>';
			$this->filter = $this->getFilterFromImage(null);
			$this->source = $event->getSource()->isEmpty()
				? '<span style="color:grey">empty</span>'
				: $event->getSource()->getId();
		} else {
			$this->action = $event->getSource()->getFilter()
				? '<span style="color:blue">filtering</span>'
				: '<span style="color:green">persist</span>';

			$this->result = $event->getResult()->getId();
			$this->filter = $this->getFilterFromImage($event->getSource());
			$this->source = $event->getSource()->getId();
		}

		$backtrace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 5);
		foreach ($backtrace as $last) {
			if (strpos($last['file'], '/vendor/') === false) {
				break;
			}
		}

		$this->entrypoint = isset($last) ? Helpers::editorLink($last['file'], $last['line']) : '';
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

	public function getEntrypoint(): string
	{
		return $this->entrypoint;
	}

	private function getFilterFromImage(?ImageInterface $image): string
	{
		if (!$image || !($filter = $image->getFilter())) {
			return '<span style="color:grey">none</span>';
		}

		return $filter->getName();
	}

}
