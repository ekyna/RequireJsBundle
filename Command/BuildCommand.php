<?php

namespace Ekyna\Bundle\RequireJsBundle\Command;

use Ekyna\Bundle\RequireJsBundle\Configuration\Provider;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class BuildCommand
 * @package Ekyna\Bundle\RequireJsBundle\Command
 * @author Étienne Dauvergne <contact@ekyna.com>
 */
class BuildCommand extends ContainerAwareCommand
{
    const MAIN_CONFIG_FILE_NAME  = 'js/require-config.js';
    const BUILD_CONFIG_FILE_NAME = 'build.js';
    const OPTIMIZER_FILE_PATH    = 'bundles/ekynarequirejs/r.js';


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('ekyna:requirejs:build')
            ->setDescription('Build single optimized js resource')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var array $config */
        $config = $this->getContainer()->getParameter('ekyna_require_js.config');
        $webRoot = realpath($config['web_root']);

        /** @var Provider $configProvider */
        $configProvider = $this->getContainer()->get('ekyna_require_js.configuration_provider');

        $output->writeln('Generating require.js main config');
        $jsonConfig = json_encode($configProvider->generateMainConfig());
        // for some reason built application gets broken with configuration in "oneline-json"
        $mainConfigContent = "require(\n" . $jsonConfig . "\n);";
        $mainConfigContent = str_replace(',', ",\n", $mainConfigContent);
        $mainConfigFilePath = $webRoot . DIRECTORY_SEPARATOR . self::MAIN_CONFIG_FILE_NAME;
        if (false === file_put_contents($mainConfigFilePath, $mainConfigContent)) {
            throw new \RuntimeException('Unable to write file ' . $mainConfigFilePath);
        }

        $output->writeln('Generating require.js build config');
        $buildConfigContent = $configProvider->generateBuildConfig(self::MAIN_CONFIG_FILE_NAME);
        $buildConfigContent = '(' . json_encode($buildConfigContent) . ')';
        $buildConfigFilePath = $webRoot . DIRECTORY_SEPARATOR . self::BUILD_CONFIG_FILE_NAME;
        if (false === file_put_contents($buildConfigFilePath, $buildConfigContent)) {
            throw new \RuntimeException('Unable to write file ' . $buildConfigFilePath);
        }

        if (isset($config['js_engine']) && $config['js_engine']) {
            $output->writeln('Running code optimizer');
            $command = $config['js_engine'] . ' ' .
                self::OPTIMIZER_FILE_PATH . ' -o ' .
                basename($buildConfigFilePath); // . ' 1>&2';
            $process = new Process($command, $webRoot);
            $process->setTimeout($config['building_timeout']);
            // some workaround when this command is launched from web
            if (isset($_SERVER['PATH'])) {
                $env = $_SERVER;
                if (isset($env['Path'])) {
                    unset($env['Path']);
                }
                $process->setEnv($env);
            }
            $process->run();
            if (!$process->isSuccessful()) {
                $output->writeln($command);
                $output->writeln($process->getOutput());
                throw new \RuntimeException($process->getErrorOutput());
            }

            $output->writeln('Cleaning up');
            if (false === unlink($buildConfigFilePath)) {
                throw new \RuntimeException('Unable to remove file ' . $buildConfigFilePath);
            }

            $output->writeln(
                sprintf(
                    '<comment>%s</comment> <info>[file+]</info> %s',
                    date('H:i:s'),
                    realpath($webRoot . DIRECTORY_SEPARATOR . $config['build_path'])
                )
            );
        }
    }
}
