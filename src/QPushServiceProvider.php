<?php

namespace EasyBib;

use Doctrine\Common\Cache\ArrayCache;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Uecode\Bundle\QPushBundle\Command\QueueBuildCommand;
use Uecode\Bundle\QPushBundle\Command\QueueDestroyCommand;
use Uecode\Bundle\QPushBundle\Command\QueuePublishCommand;
use Uecode\Bundle\QPushBundle\Command\QueueReceiveCommand;
use Uecode\Bundle\QPushBundle\Event\Events;
use Uecode\Bundle\QPushBundle\Event\MessageEvent;
use Uecode\Bundle\QPushBundle\Event\NotificationEvent;
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

                    return $pimple['uecode_qpush.providerfactory']($name, $options['options'], $providerConfig);
                };
            }

            return $queues;
        };

        $pimple['uecode_qpush.cache'] = function (Container $pimple) {
            if (isset($pimple['uecode_qpush.config']['cache']) && $pimple->offsetExists($pimple['uecode_qpush.config']['cache'])) {
                return $pimple[$pimple['uecode_qpush.config']['cache']];
            }

            return new ArrayCache();
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
                        'key' => $options['key'],
                        'secret' => $options['secret'],
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
            $command = new QueueBuildCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };

        $pimple['uecode_qpush.command.destroy'] = function (Container $pimple) {
            $command = new QueueDestroyCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };

        $pimple['uecode_qpush.command.publish'] = function (Container $pimple) {
            $command = new QueuePublishCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };

        $pimple['uecode_qpush.command.receive'] = function (Container $pimple) {
            $command = new QueueReceiveCommand();
            $command->setContainer($pimple['uecode_qpush.command.container']);

            return $command;
        };
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $log = function (Event $event) use ($app) {
            if ($event instanceof MessageEvent) {
                $app['logger']->info('Queue Message received', [
                    'queue' => $event->getQueueName(),
                    'message' => [
                        'id' => $event->getMessage()->getId(),
                        'body' => $event->getMessage()->getBody(),
                        'metadata' => $event->getMessage()->getMetadata(),
                    ],
                ]);
            } elseif ($event instanceof NotificationEvent) {
                $app['logger']->info('Queue Notification received', [
                    'queue' => $event->getQueueName(),
                    'type' => $event->getType(),
                    'notification' => [
                        'id' => $event->getNotification()->getId(),
                        'body' => $event->getNotification()->getBody(),
                        'metadata' => $event->getNotification()->getMetadata(),
                    ],
                ]);
            }

        };

        foreach ($app['uecode_qpush.config']['queues'] as $name => $options) {
            if ($options['options']['logging_enabled']) {
                $dispatcher->addListener(Events::Message($name), $log);
                $dispatcher->addListener(Events::Notification($name), $log);

                if (isset($options['queue_name'])) {
                    $dispatcher->addListener(Events::Notification($options['options']['queue_name']), $log);
                }
            }

            foreach ($options['callback'] as $callback) {
                $handleEvent = function (Event $event) use ($app, $callback) {
                    call_user_func($app['callback_resolver']->resolveCallback($callback), $event);
                };

                $dispatcher->addListener(Events::Message($name), $handleEvent);
                $dispatcher->addListener(Events::Notification($name), $handleEvent);

                if (isset($options['queue_name'])) {
                    $dispatcher->addListener(Events::Notification($options['options']['queue_name']), $log);
                }
            }
        }
    }
}
