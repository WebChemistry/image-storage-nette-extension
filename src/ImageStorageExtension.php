<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension;

use Doctrine\Common\Annotations\Reader;
use LogicException;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use WebChemistry\ImageStorage\Database\DatabaseConverter;
use WebChemistry\ImageStorage\Database\DatabaseConverterInterface;
use WebChemistry\ImageStorage\Doctrine\Annotation\AnnotationScopeProvider;
use WebChemistry\ImageStorage\File\FileFactory;
use WebChemistry\ImageStorage\File\FileFactoryInterface;
use WebChemistry\ImageStorage\Filesystem\FilesystemInterface;
use WebChemistry\ImageStorage\Filesystem\League\LeagueFilesystemFactoryInterface;
use WebChemistry\ImageStorage\Filesystem\League\LocalLeagueFilesystemFactory;
use WebChemistry\ImageStorage\Filesystem\LocalFilesystem;
use WebChemistry\ImageStorage\Filter\FilterNormalizerCollection;
use WebChemistry\ImageStorage\Filter\FilterNormalizerCollectionInterface;
use WebChemistry\ImageStorage\Filter\FilterProcessorInterface;
use WebChemistry\ImageStorage\ImageStorageInterface;
use WebChemistry\ImageStorage\ImagineFilters\FilterProcessor;
use WebChemistry\ImageStorage\ImagineFilters\OperationInterface;
use WebChemistry\ImageStorage\ImagineFilters\OperationRegistry;
use WebChemistry\ImageStorage\ImagineFilters\OperationRegistryInterface;
use WebChemistry\ImageStorage\LinkGenerator\LinkGenerator as LegacyLinkGenerator;
use WebChemistry\ImageStorage\LinkGeneratorInterface;
use WebChemistry\ImageStorage\NetteBridge\LinkGenerator;
use WebChemistry\ImageStorage\PathInfo\PathInfoFactory;
use WebChemistry\ImageStorage\PathInfo\PathInfoFactoryInterface;
use WebChemistry\ImageStorage\Resolver\BucketResolverInterface;
use WebChemistry\ImageStorage\Resolver\BucketResolvers\BucketResolver;
use WebChemistry\ImageStorage\Resolver\FileNameResolverInterface;
use WebChemistry\ImageStorage\Resolver\FileNameResolvers\PrefixFileNameResolver;
use WebChemistry\ImageStorage\Resolver\FilterResolverInterface;
use WebChemistry\ImageStorage\Resolver\FilterResolvers\OriginalFilterResolver;
use WebChemistry\ImageStorage\Storage\ImageStorage;

final class ImageStorageExtension extends CompilerExtension
{

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$this->loadFilesystem($builder);
		$this->loadResolvers($builder);
		$this->loadPathInfo($builder);
		$this->loadFile($builder);
		$this->loadDatabase($builder);
		$this->loadDoctrine($builder);
		$this->loadFilter($builder);

		if (interface_exists(OperationInterface::class)) {
			$this->loadImageFiltersExtension($builder);
		}

		$builder->addDefinition($this->prefix('storage'))
			->setType(ImageStorageInterface::class)
			->setFactory(ImageStorage::class);

		$legacy = $builder->addDefinition($this->prefix('legacyLinkGenerator'))
			->setType(LinkGeneratorInterface::class)
			->setFactory(LegacyLinkGenerator::class)
			->setAutowired(false);

		$builder->addDefinition($this->prefix('linkGenerator'))
			->setType(LinkGeneratorInterface::class)
			->setFactory(LinkGenerator::class, [$legacy]);
	}

	private function loadFilesystem(ContainerBuilder $builder): void
	{
		if (!isset($builder->parameters['wwwDir'])) {
			throw new LogicException('Neon parameter %wwwDir% must be configured');
		}

		$builder->addDefinition($this->prefix('filesystem.leagueFactory'))
			->setType(LeagueFilesystemFactoryInterface::class)
			->setFactory(LocalLeagueFilesystemFactory::class, [
				$builder->parameters['wwwDir']
			]);

		$builder->addDefinition($this->prefix('filesystem'))
			->setType(FilesystemInterface::class)
			->setFactory(LocalFilesystem::class);
	}

	private function loadResolvers(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('resolvers.bucket'))
			->setType(BucketResolverInterface::class)
			->setFactory(BucketResolver::class);

		$builder->addDefinition($this->prefix('resolvers.fileName'))
			->setType(FileNameResolverInterface::class)
			->setFactory(PrefixFileNameResolver::class);

		$builder->addDefinition($this->prefix('resolvers.filter'))
			->setType(FilterResolverInterface::class)
			->setFactory(OriginalFilterResolver::class);
	}

	private function loadPathInfo(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('pathInfoFactory'))
			->setType(PathInfoFactoryInterface::class)
			->setFactory(PathInfoFactory::class);
	}

	private function loadFile(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('fileFactory'))
			->setType(FileFactoryInterface::class)
			->setFactory(FileFactory::class);
	}

	private function loadDatabase(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('database.converter'))
			->setType(DatabaseConverterInterface::class)
			->setFactory(DatabaseConverter::class);
	}

	private function loadFilter(ContainerBuilder $builder)
	{
		$builder->addDefinition($this->prefix('filter.normalizerCollection'))
			->setType(FilterNormalizerCollectionInterface::class)
			->setFactory(FilterNormalizerCollection::class);
	}

	private function loadImageFiltersExtension(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('imagine.filterProcessor'))
			->setType(FilterProcessorInterface::class)
			->setFactory(FilterProcessor::class);

		$builder->addDefinition($this->prefix('imagine.operationRegistry'))
			->setType(OperationRegistryInterface::class)
			->setFactory(OperationRegistry::class);
	}

	private function loadDoctrine(ContainerBuilder $builder): void
	{
		if (!interface_exists(Reader::class)) {
			return;
		}

		$builder->addDefinition($this->prefix('doctrine.annotations.scopeProvider'))
			->setFactory(AnnotationScopeProvider::class);
	}

}
