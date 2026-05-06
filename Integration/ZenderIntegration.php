<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticZenderBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * Class ZenderIntegration.
 */
class ZenderIntegration extends AbstractIntegration
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getName()
    {
        return 'Zender';
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getIcon()
    {
        return 'plugins/MauticZenderBundle/Assets/img/whatsapp.png';
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getSecretKeys()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getRequiredKeyFields()
    {
        return [
            'zender_api_key' => 'mautic.plugin.zender.api_key',
            'zender_api_url' => 'mautic.plugin.zender.api_url',
            'shortener_url'  => 'mautic.plugin.zender.shortener_url',
        ];
    }

    /**
     * @return array
     */
    public function getFormSettings(): array
    {
        return [
            'requires_callback'      => false,
            'requires_authorization' => false,
        ];
    }

    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ('auth' === $formArea) {
            $builder->add(
                'zender_api_key',
                TextType::class,
                [
                    'label'    => 'mautic.plugin.zender.api_key',
                    'required' => true,
                    'attr'     => [
                        'class' => 'form-control',
                    ],
                ]
            );

            $builder->add(
                'zender_api_url',
                TextType::class,
                [
                    'label'    => 'mautic.plugin.zender.api_url',
                    'required' => true,
                    'attr'     => [
                        'class' => 'form-control',
                    ],
                ]
            );

            $builder->add(
                'shortener_url',
                TextType::class,
                [
                    'label'    => 'mautic.plugin.zender.shortener_url',
                    'required' => true,
                    'attr'     => [
                        'class' => 'form-control',
                    ],
                ]
            );
        }

        if ('features' === $formArea) {
            $builder->add(
                'fetch_quantity',
                TextType::class,
                [
                    'label'    => 'mautic.plugin.zender.fetch_quantity',
                    'required' => true,
                    'attr'     => [
                        'class' => 'form-control',
                    ],
                    'help' => 'mautic.plugin.zender.fetch_quantity.description',
                ]
            );

            $builder->add(
                'fetch_unit',
                ChoiceType::class,
                [
                    'choices'  => [
                        'Minutes' => 'minutes',
                        'Hours'   => 'hours',
                        'Days'    => 'days',
                        'Weeks'   => 'weeks',
                        'Months'  => 'months',
                        'Years'   => 'years',
                    ],
                    'label'    => 'mautic.plugin.zender.fetch_unit',
                    'required' => true,
                    'attr'     => [
                        'class' => 'form-control',
                    ],
                    'help' => 'mautic.plugin.zender.fetch_unit.description',
                ]
            );

            $builder->add(
                'batch_size',
                TextType::class,
                [
                    'label'    => 'mautic.plugin.zender.batch_size',
                    'required' => true,
                    'attr'     => [
                        'class' => 'form-control',
                    ],
                    'help' => 'mautic.plugin.zender.batch_size.description',
                ]
            );

            $builder->add(
                'plugin_description',
                TextareaType::class,
                [
                    'label'    => false,
                    'required' => false,
                    'attr'     => [
                        'class'    => 'form-control',
                        'readonly' => true,
                        'style'    => 'height: 200px;',
                    ],
                    'data' => 'This plugin allows you to send messages to WhatsApp using a Zender account. '
                        .'Configure the API key, API URL, and Shortener URL in the Enabled/Auth tab. '
                        .'In the Features tab, set the Fetch Quantity, Fetch Unit, and Batch Size to control '
                        .'how messages are fetched and processed. Use the API URL only up to the /api part. '
                        .'For detailed instructions, refer to the documentation at: https://github.com/AlexanderZlobinM1/MauticZenderPlugin',
                ]
            );
        }
    }

    public function getFormAreas(): array
    {
        return [
            'auth' => [
                'label'       => 'mautic.integration.auth',
                'description' => 'mautic.integration.auth_desc',
            ],
            'features' => [
                'label'       => 'mautic.integration.features',
                'description' => 'mautic.integration.features_desc',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'none';
    }
}
