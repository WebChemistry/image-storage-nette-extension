<?php declare(strict_types = 1);

namespace unit;

use Codeception\Test\Unit;
use Nette\Bridges\HttpDI\HttpExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use WebChemistry\ImageStorage\Filter\FilterProcessorInterface;
use WebChemistry\ImageStorage\ImagineFilters\FilterProcessor;
use WebChemistry\ImageStorage\LinkGeneratorInterface;
use WebChemistry\ImageStorage\NetteExtension\DI\ImageStorageExtension;
use WebChemistry\ImageStorage\NetteExtension\LinkGenerator;

final class NetteDITest extends Unit
{

	private TemporaryDirectory $tempDir;

	private Container $container;

	protected function _before(): void
	{
		$this->tempDir = new TemporaryDirectory(__DIR__ . '/_tmp');
		$this->tempDir->delete();

		$loader = new ContainerLoader($this->tempDir->path('di'));
		$class = $loader->load(function (Compiler $compiler): void {
			$compiler->addConfig([
				'parameters' => [
					'wwwDir' => $this->tempDir->path('www'),
				],
			]);
			$compiler->addExtension('http', new HttpExtension());
			$compiler->addExtension('imageStorage', new ImageStorageExtension());
		});

		$this->container = new $class();
	}

	protected function _after(): void
	{
		$this->tempDir->delete();
	}

	public function testCompilerClasses(): void
	{
		$this->assertInstanceOf(LinkGenerator::class, $this->container->getByType(LinkGeneratorInterface::class));
		$this->assertInstanceOf(FilterProcessor::class, $this->container->getByType(FilterProcessorInterface::class));
	}

}
