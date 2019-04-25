<?php

namespace Ekyna\Bundle\RequireJsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class EkynaRequireJsExtension
 * @package Ekyna\Bundle\RequireJsBundle\DependencyInjection
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class EkynaRequireJsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $config['env'] = $container->getParameter('kernel.environment');

        $strategy = $config['asset_strategy'];
        unset($config['asset_strategy']);

        $provider = $container
            ->getDefinition('ekyna_require_js.configuration_provider')
            ->replaceArgument(1, $config);

        if (!empty($strategy)) {
            $provider->addMethodCall('setVersionStrategy', [new Reference($strategy)]);
        }
    }
}
