<?php

namespace EasyBib;

use Doctrine\Common\Cache\ArrayCache;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Uecode\Bundle\QPushBundle\Command\QueueBuildCommand;
use Uecode\Bundle\QPushBundle\Command\QueueDestroyCommand;
use Uecode\Bundle\QPushBundle\Command\QueuePublishCommand;
use Uecode\Bundle\QPushBundle\Command\QueueReceiveCommand;
use Uecode\Bundle\QPushBundle\Provider\AwsProvider;
use Uecode\Bundle\QPushBundle\Provider\ProviderRegistry;

class QPushServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        // Alias
        $pimple['uecode_qpush'] = function (Container $pimple) {
            return $pimple['uecode_qpush.registry'];
        };

        $pimple['uecode_qpush.registry'] = function (Container $pimple) {
            $registry = new ProviderRegistry();
            foreach (array_keys($pimple['uecode_qpush.config']['queues']) as $name) {
                $registry->addProvider($name, $pimple['uecode_qpush.queues'][$name]);
            }

            return $registry;
        };

        $pimple['uecode_qpush.queues'] = function (Container $pimple) {
            $queues = new Container();
            foreach ($pimple['uecode_qpush.config']['queues'] as $name => $options) {
                $queues[$name] = function () use ($name, $options, $pimple) {
                    $providerConfig = $pimple['uecode_qpush.config']['providers'][$options['provider']];

                    return $pimple['uecode_qpush.providerfactory']($name, $options, $providerConfig);
                };
            }

            return $queues;
        };

        $pimple['uecode_qpush.cache'] = function (Container $pimple) {
            $cacheClass = isset($pimple['uecode_qpush.config']['cache']) ? $pimple['uecode_qpush.config']['cache'] : ArrayCache::class;

            return new $cacheClass();
        };

        $pimple['uecode_qpush.providerfactory'] = $pimple->protect(function ($name, $options, $providerConfig) use ($pimple) {
            switch ($providerConfig['driver']) {
                case 'aws':
                    return new AwsProvider($name, $options, $pimple['uecode_qpush.awsfactory']($providerConfig), $pimple['uecode_qpush.cache'], $pimple['logger']);
            }
        });

        $pimple['uecode_qpush.awsfactory'] = $pimple->protect(function ($options) {
            $aws2 = class_exists('Aws\Common\Aws');
            $aws3 = class_exists('Aws\Sdk');
            if (!$aws2 && !$aws3) {
                throw new \RuntimeException(
                    'You must require "aws/aws-sdk-php" to use the AWS provider.'
                );
            }

            if ($aws2) {
                return \Aws\Common\Aws::factory($options);
            } else {
                if (!empty($options['key']) && !empty($options['secret'])) {
                    $options['credentials'] = [
                        'key'    => $options['key'],
                        'secret' => $options['secret']
                    ];
                }
                $options['version'] = 'latest';

                return new \Aws\Sdk($options);
            }
        });

        $pimple['uecode_qpush.command.container'] = function (Container $pimple) {
            $container = new \Symfony\Component\DependencyInjection\Container();
            $container->set('uecode_qpush', $pimple['uecode_qpush.registry']);
            $container->set('event_dispatcher', $pimple['dispatcher']);

            return $container;
        };

        $pimple['uecode_qpush.command.build'] = function (Container $pimple) {
            $command =  new QueueBuildCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };

        $pimple['uecode_qpush.command.destroy'] = function (Container $pimple) {
            $command =  new QueueDestroyCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };

        $pimple['uecode_qpush.command.publish'] = function (Container $pimple) {
            $command =  new QueuePublishCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };

        $pimple['uecode_qpush.command.receive'] = function (Container $pimple) {
            $command =  new QueueReceiveCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };
    }
}
