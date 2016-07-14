# silex-qpush-provider
Silex service provider for uecode/qpush-bundle  

### Setup
```php
$app->register(new \EasyBib\QPushServiceProvider(), [
    'uecode_qpush.config' => [
        'cache' => 'my.cache.service',
        'providers' => [
            'aws' => [
                'driver' => 'aws',
                'key' => 'key',
                'secret' => 'secret',
                'region' => 'us-east-1',
            ],
        ],
        'queues' => [
            'my_queue' => [
                'provider' => 'aws',
                'callback' => [
                   'service:method',
                ],
                'options' => [
                    'logging_enabled' => true,
                    'queue_name' => 'my_queue',
                    'push_notifications' => false,
                    'message_delay' =>  0,
                    'message_timeout' => 30,
                    'message_expiration' => 604800,
                    'messages_to_receive' => 1,
                    'receive_wait_time' => 3,
                ],
            ],
        ],
    ],
]);
```

#### Local FakeSQS setup
For local setup/testing you might want to use [Fake SQS](https://github.com/iain/fake_sqs) as Amazon SQS replacement.
```php
$app->register(new \EasyBib\QPushServiceProvider(), [
    'uecode_qpush.config' => [
        'providers' => [
            'fakesqs' => [
                'driver' => 'aws',
                'key' => 'fake key',
                'secret' => 'fake secret',
                'region' => 'us-east-1', // Needs to be set but won't be used
                'base_url' => "http://localhost:4568",
            ],
        ],
    ],
]);
```
