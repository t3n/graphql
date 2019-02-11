<?php

declare(strict_types=1);

namespace t3n\GraphQL;

use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Sequence;
use Neos\Flow\Core\Booting\Step;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Monitor\FileMonitor;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Flow\Package\PackageManagerInterface;
use Neos\Utility\Files;
use Neos\Utility\Unicode\Functions;
use function count;
use function is_array;
use function substr;

class Package extends BasePackage
{
    protected const FILE_MONITOR_IDENTIFIER = 't3n_GraphQL_Files';

    /**
     * @param mixed[] $configuration
     */
    protected function monitorTypeDefResources(array $configuration, PackageManagerInterface $packageManager, FileMonitor $fileMonitor): void
    {
        $schemas = $configuration['schemas'] ?? null;
        if (is_array($schemas)) {
            foreach ($schemas as $schemaConfiguration) {
                $this->monitorTypeDefResources($schemaConfiguration, $packageManager, $fileMonitor);
            }
            return;
        }

        $typeDefs = $configuration['typeDefs'] ?? null;

        if (! $typeDefs) {
            return;
        }

        if (substr($typeDefs, 0, 11) !== 'resource://') {
            return;
        }

        $uriParts = Functions::parse_url($typeDefs);
        /** @var BasePackage $package */
        $package      = $packageManager->getPackage($uriParts['host']);
        $absolutePath = Files::concatenatePaths([$package->getResourcesPath(), $uriParts['path']]);
        $fileMonitor->monitorFile($absolutePath);
    }

    public function boot(Bootstrap $bootstrap): void
    {
        if ($bootstrap->getContext()->isProduction()) {
            return;
        }

        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(Sequence::class, 'afterInvokeStep', function (Step $step) use ($bootstrap): void {
            if ($step->getIdentifier() !== 'neos.flow:systemfilemonitor') {
                return;
            }

            $graphQLFileMonitor     = FileMonitor::createFileMonitorAtBoot(static::FILE_MONITOR_IDENTIFIER, $bootstrap);
            $configurationManager   = $bootstrap->getEarlyInstance(ConfigurationManager::class);
            $packageManager         = $bootstrap->getEarlyInstance(PackageManagerInterface::class);
            $endpointsConfiguration = $configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                't3n.GraphQL.endpoints'
            );

            foreach ($endpointsConfiguration as $endpointConfiguration) {
                $this->monitorTypeDefResources($endpointConfiguration, $packageManager, $graphQLFileMonitor);
            }

            $graphQLFileMonitor->detectChanges();
            $graphQLFileMonitor->shutdownObject();
        });

        $dispatcher->connect(
            FileMonitor::class,
            'filesHaveChanged',
            static function (string $fileMonitorIdentifier, array $changedFiles) use ($bootstrap): void {
                if ($fileMonitorIdentifier !== static::FILE_MONITOR_IDENTIFIER || count($changedFiles) === 0) {
                    return;
                }

                /** @var CacheManager $cacheManager */
                $cacheManager = $bootstrap->getObjectManager()->get(CacheManager::class);
                $cacheManager->getCache('t3n_GraphQL_Schema')->flush();
            }
        );
    }
}
