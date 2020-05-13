<?php declare(strict_types = 1);

use Codeception\Test\Unit;
use Nette\Bridges\HttpDI\HttpExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use WebChemistry\ImageStorage\LinkGeneratorInterface;
use WebChemistry\ImageStorage\NetteBridge\LinkGenerator;
use WebChemistry\ImageStorage\NetteExtension\ImageStorageExtension;

final class NetteDITest extends Unit
{

	private TemporaryDirectory $tempDir;
	private Container $container;

	protected function _before()
	{
		$this->tempDir = new TemporaryDirectory(__DIR__ . '/_tmp');
		$this->tempDir->delete();

		$loader = new ContainerLoader($this->tempDir->path('di'));
		$class = $loader->load(function (Compiler $compiler): void {
			$compiler->addConfig([
				'parameters' => [
					'wwwDir' => $this->tempDir->path('www'),
				]
			]);
			$compiler->addExtension('http', new HttpExtension());
			$compiler->addExtension('imageStorage', new ImageStorageExtension());
		});

		$this->container = new $class;
	}

	protected function _after()
	{
		$this->tempDir->delete();
	}

	public function testCompilerClasses(): void
	{
		$this->assertInstanceOf(LinkGenerator::class, $this->container->getByType(LinkGeneratorInterface::class));
	}

}
