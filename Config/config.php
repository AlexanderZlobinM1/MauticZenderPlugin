<?php

$integrationArguments = [
    'event_dispatcher',
    'mautic.helper.cache_storage',
    'doctrine.orm.entity_manager',
];

// Mautic 5 expects `session`; Mautic 6/7 removed it and added FieldsWithUniqueIdentifier.
if (class_exists(\Mautic\LeadBundle\Field\FieldsWithUniqueIdentifier::class)) {
    $integrationArguments = array_merge($integrationArguments, [
        'request_stack',
        'router',
        'translator',
        'logger',
        'mautic.helper.encryption',
        'mautic.lead.model.lead',
        'mautic.lead.model.company',
        'mautic.helper.paths',
        'mautic.core.model.notification',
        'mautic.lead.model.field',
        'mautic.plugin.model.integration_entity',
        'mautic.lead.model.dnc',
        'mautic.lead.field.fields_with_unique_identifier',
    ]);
} else {
    $integrationArguments = array_merge($integrationArguments, [
        'session',
        'request_stack',
        'router',
        'translator',
        'logger',
        'mautic.helper.encryption',
        'mautic.lead.model.lead',
        'mautic.lead.model.company',
        'mautic.helper.paths',
        'mautic.core.model.notification',
        'mautic.lead.model.field',
        'mautic.plugin.model.integration_entity',
        'mautic.lead.model.dnc',
    ]);
}

return [
    'name'         => 'Zender',
    'description'  => 'This plugin replaces the SMS channel and allows you to send messages to WhatsApp using a Zender account. Intended for Mautic 5/6/7',
    'author'       => 'AlexanderZlobinM1',
    'version'      => '1.2.12',
    'release_date' => '2026-07-01',
    'license'      => 'GNU/GPLv3',
    'homepage'     => 'https://github.com/AlexanderZlobinM1/MauticZenderPlugin',
    'support'      => 'https://github.com/AlexanderZlobinM1/MauticZenderPlugin/issues',
    'requirements' => [
        'mautic' => '>=5.1.0 <8.0.0',
        'php'    => '>=8.1',
        'dependencies' => [
            'zender' => 'https://codecanyon.net/item/zender-android-mobile-devices-as-sms-gateway-saas-platform/26594230',
        ],
    ],
    'last_updated' => '2026-07-01',
    'services' => [
        'events' => [
            'mautic.zender.plugin_activate.subscriber' => [
                'class' => 'MauticPlugin\MauticZenderBundle\EventListener\PluginActivatedEventListener',
                'arguments' => [
                    'mautic.lead.model.field',
                ],
            ],
        ],
        'forms'   => [],
        'helpers' => [],
        'command' => [
            'mautic.zender.command.sync_messages' => [
                'class'     => \MauticPlugin\MauticZenderBundle\Command\SyncMessagesCommand::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.helper.integration',
                    'mautic.lead.model.lead',
                    'monolog.logger.mautic',
                    'mautic.http.client',
                    'mautic.helper.core_parameters',
                ],
                'tag'       => 'console.command',
            ],
        ],
        'models'  => [],
        'other'   => [
            'mautic.sms.transport.zender' => [
                'class'     => \MauticPlugin\MauticZenderBundle\Transport\ZenderTransport::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'monolog.logger.mautic',
                    'mautic.http.client',
                    'doctrine.orm.entity_manager',
                ],
                'alias'        => 'mautic.sms.config.zender.transport',
                'tag'          => 'mautic.sms_transport',
                'tagArguments' => [
                    'integrationAlias' => 'Zender',
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.zender' => [
                'class' => \MauticPlugin\MauticZenderBundle\Integration\ZenderIntegration::class,
                'arguments' => $integrationArguments,
            ],
        ],
    ],
    'routes'     => [
        'public' => [
            'mautic_zender_receive_webhook_legacy' => [
                'path'       => '/zender/receive/{key}/{phone}/{message}/{time}/{datetime}',
                'controller' => \MauticPlugin\MauticZenderBundle\Controller\ZenderWebhookController::class.'::receiveAction',
                'method'     => 'GET',
            ],
            'mautic_zender_receive_webhook_get' => [
                'path'       => '/zender/receive/{key}',
                'controller' => \MauticPlugin\MauticZenderBundle\Controller\ZenderWebhookController::class.'::receiveAction',
                'method'     => 'GET',
            ],
            'mautic_zender_receive_webhook_post' => [
                'path'       => '/zender/receive/{key}',
                'controller' => \MauticPlugin\MauticZenderBundle\Controller\ZenderWebhookController::class.'::receiveAction',
                'method'     => 'POST',
            ],
        ],
    ],
    'menu'       => [
        'main' => [
            'items' => [
                'mautic.zender.smses' => [
                    'route'    => 'mautic_sms_index',
                    'access'   => ['sms:smses:viewown', 'sms:smses:viewother'],
                    'parent'   => 'mautic.core.channels',
                    'checks'   => [
                        'integration' => [
                            'Zender' => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                    'priority' => 70,
                ],
            ],
        ],
    ],
    'parameters' => [],
];
