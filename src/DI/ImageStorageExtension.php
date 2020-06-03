<?php declare(strict_types = 1);

namespace WebChemistry\ImageStorage\NetteExtension\DI;

use Doctrine\Common\Annotations\Reader;
use Doctrine\DBAL\Driver\Connection;
use LogicException;
use Nette\Bridges\ApplicationLatte\ILatteFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\FactoryDefinition;
use Nette\DI\Definitions\ServiceDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tracy\Bar;
use Tracy\IBarPanel;
use WebChemistry\ImageStorage\Database\DatabaseConverter;
use WebChemistry\ImageStorage\Database\DatabaseConverterInterface;
use WebChemistry\ImageStorage\Doctrine\Annotation\AnnotationScopeProvider;
use WebChemistry\ImageStorage\Doctrine\ImageType;
use WebChemistry\ImageStorage\File\FileFactory;
use WebChemistry\ImageStorage\File\FileFactoryInterface;
use WebChemistry\ImageStorage\Filesystem\FilesystemInterface;
use WebChemistry\ImageStorage\Filesystem\LocalFilesystem;
use WebChemistry\ImageStorage\Filter\FilterNormalizerCollection;
use WebChemistry\ImageStorage\Filter\FilterNormalizerCollectionInterface;
use WebChemistry\ImageStorage\Filter\FilterNormalizerInterface;
use WebChemistry\ImageStorage\Filter\FilterProcessorInterface;
use WebChemistry\ImageStorage\Filter\VoidFilterProcessor;
use WebChemistry\ImageStorage\ImageStorageInterface;
use WebChemistry\ImageStorage\ImagineFilters\FilterProcessor;
use WebChemistry\ImageStorage\ImagineFilters\OperationInterface;
use WebChemistry\ImageStorage\ImagineFilters\OperationRegistry;
use WebChemistry\ImageStorage\ImagineFilters\OperationRegistryInterface;
use WebChemistry\ImageStorage\LinkGenerator\LinkGenerator as LegacyLinkGenerator;
use WebChemistry\ImageStorage\LinkGeneratorInterface;
use WebChemistry\ImageStorage\NetteExtension\Latte\LatteImageProvider;
use WebChemistry\ImageStorage\NetteExtension\LinkGenerator;
use WebChemistry\ImageStorage\NetteExtension\Macro\ImageMacro;
use WebChemistry\ImageStorage\NetteExtension\Tracy\ImageBarPanel;
use WebChemistry\ImageStorage\PathInfo\PathInfoFactory;
use WebChemistry\ImageStorage\PathInfo\PathInfoFactoryInterface;
use WebChemistry\ImageStorage\Persister\EmptyImagePersister;
use WebChemistry\ImageStorage\Persister\PersistentImagePersister;
use WebChemistry\ImageStorage\Persister\PersisterInterface;
use WebChemistry\ImageStorage\Persister\PersisterRegistry;
use WebChemistry\ImageStorage\Persister\PersisterRegistryInterface;
use WebChemistry\ImageStorage\Persister\StorableImagePersister;
use WebChemistry\ImageStorage\Remover\EmptyImageRemover;
use WebChemistry\ImageStorage\Remover\PersistentImageRemover;
use WebChemistry\ImageStorage\Remover\RemoverInterface;
use WebChemistry\ImageStorage\Remover\RemoverRegistry;
use WebChemistry\ImageStorage\Remover\RemoverRegistryInterface;
use WebChemistry\ImageStorage\Resolver\BucketResolverInterface;
use WebChemistry\ImageStorage\Resolver\BucketResolvers\BucketResolver;
use WebChemistry\ImageStorage\Resolver\DefaultImageResolverInterface;
use WebChemistry\ImageStorage\Resolver\DefaultImageResolvers\NullDefaultImageResolver;
use WebChemistry\ImageStorage\Resolver\FileNameResolverInterface;
use WebChemistry\ImageStorage\Resolver\FileNameResolvers\PrefixFileNameResolver;
use WebChemistry\ImageStorage\Resolver\FilterResolverInterface;
use WebChemistry\ImageStorage\Resolver\FilterResolvers\OriginalFilterResolver;
use WebChemistry\ImageStorage\Storage\ImageStorage;
use WebChemistry\ImageStorage\Transaction\TransactionFactory;
use WebChemistry\ImageStorage\Transaction\TransactionFactoryInterface;

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
		$this->loadLatte($builder);
		$this->loadDebugger($builder);
		$this->loadPersister($builder);
		$this->loadRemover($builder);

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

		$builder->addDefinition($this->prefix('transactionFactory'))
			->setType(TransactionFactoryInterface::class)
			->setFactory(TransactionFactory::class);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$this->injectNormalizers($builder);
		$this->injectRemovers($builder);
		$this->injectPersisters($builder);

		$serviceName = $builder->getByType(Bar::class);
		if ($serviceName) {
			$this->assertServiceDefinition($builder->getDefinition($serviceName))
				->addSetup('addPanel', [$builder->getDefinition($this->prefix('tracy.bar'))]);
		}

		$serviceName = $builder->getByType(EventDispatcherInterface::class, false);
		if ($serviceName) {
			$this->assertServiceDefinition($builder->getDefinition($serviceName))
				->addSetup('addSubscriber', [$builder->getDefinition($this->prefix('tracy.bar'))]);
		}
	}

	private function injectNormalizers(ContainerBuilder $builder): void
	{
		$service = $builder->getDefinition($this->prefix('filter.normalizerCollection'));
		assert($service instanceof ServiceDefinition);

		foreach ($builder->findByType(FilterNormalizerInterface::class) as $normalizer) {
			$service->addSetup('add', [$normalizer]);
		}
	}

	private function injectPersisters(ContainerBuilder $builder): void
	{
		$service = $builder->getDefinition($this->prefix('persisterRegistry'));
		assert($service instanceof ServiceDefinition);

		foreach ($builder->findByType(PersisterInterface::class) as $persister) {
			$service->addSetup('add', [$persister]);
		}
	}

	private function injectRemovers(ContainerBuilder $builder): void
	{
		$service = $builder->getDefinition($this->prefix('removerRegistry'));
		assert($service instanceof ServiceDefinition);

		foreach ($builder->findByType(RemoverInterface::class) as $remover) {
			$service->addSetup('add', [$remover]);
		}
	}

	private function loadFilesystem(ContainerBuilder $builder): void
	{
		if (!isset($builder->parameters['wwwDir'])) {
			throw new LogicException('Neon parameter %wwwDir% must be configured');
		}

		$builder->addDefinition($this->prefix('filesystem'))
			->setType(FilesystemInterface::class)
			->setFactory(LocalFilesystem::class, [
				$builder->parameters['wwwDir'],
			]);
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

		$builder->addDefinition($this->prefix('resolvers.defaultImage'))
			->setType(DefaultImageResolverInterface::class)
			->setFactory(NullDefaultImageResolver::class);
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

	private function loadFilter(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('filter.normalizerCollection'))
			->setType(FilterNormalizerCollectionInterface::class)
			->setFactory(FilterNormalizerCollection::class);

		$builder->addDefinition($this->prefix('filterProcessor'))
			->setType(FilterProcessorInterface::class)
			->setFactory(VoidFilterProcessor::class);
	}

	private function loadImageFiltersExtension(ContainerBuilder $builder): void
	{
		if (!interface_exists(FilterProcessorInterface::class)) {
			return;
		}

		$this->assertServiceDefinition($builder->getDefinition($this->prefix('filterProcessor')))
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

		$serviceName = $builder->getByType(Connection::class);
		if (!$serviceName) {
			return;
		}

		$builder->addDefinition($this->prefix('doctrine.annotations.scopeProvider'))
			->setFactory(AnnotationScopeProvider::class);

		$this->assertServiceDefinition($builder->getDefinition($serviceName))
			->addSetup('?::register(?)', [ImageType::class, '@self']);
	}

	private function loadLatte(ContainerBuilder $builder): void
	{
		$serviceName = $builder->getByType(ILatteFactory::class);
		if (!$serviceName) {
			return;
		}

		$builder->addDefinition($this->prefix('latte.provider'))
			->setFactory(LatteImageProvider::class);

		$factory = $builder->getDefinition($serviceName);
		assert($factory instanceof FactoryDefinition);

		$factory->getResultDefinition()
			->addSetup('?->onCompile[] = function ($engine) { ?::install($engine->getCompiler()); }', [
				'@self',
				ImageMacro::class,
			])
			->addSetup('addProvider', ['images', $this->prefix('@latte.provider')]);
	}

	private function loadDebugger(ContainerBuilder $builder): void
	{
		if (!interface_exists(EventSubscriberInterface::class)) {
			return;
		}

		$builder->addDefinition($this->prefix('tracy.bar'))
			->setType(IBarPanel::class)
			->setFactory(ImageBarPanel::class)
			->setAutowired(false);
	}

	private function loadPersister(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('persisterRegistry'))
			->setType(PersisterRegistryInterface::class)
			->setFactory(PersisterRegistry::class);

		$builder->addDefinition($this->prefix('persisters.emptyImage'))
			->setType(PersisterInterface::class)
			->setFactory(EmptyImagePersister::class);

		$builder->addDefinition($this->prefix('persisters.storableImage'))
			->setType(PersisterInterface::class)
			->setFactory(StorableImagePersister::class);

		$builder->addDefinition($this->prefix('persisters.persistentImage'))
			->setType(PersisterInterface::class)
			->setFactory(PersistentImagePersister::class);
	}

	private function loadRemover(ContainerBuilder $builder): void
	{
		$builder->addDefinition($this->prefix('removerRegistry'))
			->setType(RemoverRegistryInterface::class)
			->setFactory(RemoverRegistry::class);

		$builder->addDefinition($this->prefix('removers.emptyImage'))
			->setType(RemoverInterface::class)
			->setFactory(EmptyImageRemover::class);

		$builder->addDefinition($this->prefix('removers.persisterImage'))
			->setType(RemoverInterface::class)
			->setFactory(PersistentImageRemover::class);
	}

	private function assertServiceDefinition(Definition $definition): ServiceDefinition
	{
		assert($definition instanceof ServiceDefinition);

		return $definition;
	}

}
