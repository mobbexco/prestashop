<?php

defined('_PS_VERSION_') || exit;

return [
    'form' => [
        'tabs'        => [
            'tab_general'    => $this->l('General'),
            'tab_appearence' => $this->l('Appearance'),
            'tab_advanced'   => $this->l('Advanced Configuration'),
        ],
        'legend'      => [
            'title' => $this->l('Settings'),
            'icon'  => 'icon-cogs',
        ],
        'submit'      => [
            'title' => $this->l('Save'),
        ],
        'success'     => null,
        'description' => null,
        'warning'     => null,
        'error'       => null,
        'input'       => [
            [
                'type' => 'text',
                'label' => $this->l('API Key'),
                'name' => MobbexHelper::K_API_KEY,
                'required' => true,
                'tab' => 'tab_general'
            ],
            [
                'type' => 'text',
                'label' => $this->l('Access Token'),
                'name' => MobbexHelper::K_ACCESS_TOKEN,
                'required' => true,
                'tab' => 'tab_general'
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Test Mode'),
                'name' => MobbexHelper::K_TEST_MODE,
                'is_bool' => true,
                'required' => true,
                'values' => [
                    [
                        'id' => 'active_on_mdv',
                        'value' => true,
                        'label' => $this->l('Test Mode'),
                    ],
                    [
                        'id' => 'active_off_mdv',
                        'value' => false,
                        'label' => $this->l('Live Mode'),
                    ],
                ],
                'tab' => 'tab_general'
            ],
            [
                'type'     => 'text',
                'label'    => $this->l('Título del medio de pago'),
                'name'     => 'MOBBEX_TITLE',
                'required' => false,
                'desc'     => $this->l('En caso de tener varios métodos de pago, sólo se remplazará el texto del medio "Tarjeta de Crédito/Débito"'),
                'tab'      => 'tab_appearence',
                'default'  => '',
            ],
            [
                'type' => 'radio',
                'label' => $this->l('Theme Mode'),
                'name' => MobbexHelper::K_THEME,
                'is_bool' => false,
                'required' => false,
                'tab' => 'tab_appearence',
                'values' => [
                    [
                        'id' => 'm_theme_light',
                        'value' => MobbexHelper::K_THEME_LIGHT,
                        'label' => $this->l('Light Mode'),
                    ],
                    [
                        'id' => 'm_theme_dark',
                        'value' => MobbexHelper::K_THEME_DARK,
                        'label' => $this->l('Dark Mode'),
                    ],
                ],
                'default' => MobbexHelper::K_DEF_THEME,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Background Color'),
                'name' => MobbexHelper::K_THEME_BACKGROUND,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Checkout Background Color'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_BACKGROUND,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Primary Color'),
                'name' => MobbexHelper::K_THEME_PRIMARY,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Checkout Primary Color'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PRIMARY,
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Utilizar logo configurado en la tienda (prestashop)'),
                'desc' => "Al desactivarse se utilizará el logo configurado en la cuenta de Mobbex.",
                'name' => MobbexHelper::K_THEME_SHOP_LOGO,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_appearence',
                'values' => [
                    [
                        'id' => 'active_on_shop_logo',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_shop_logo',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type' => 'text',
                'label' => $this->l('Logo Personalizado ( URL )'),
                'name' => MobbexHelper::K_THEME_LOGO,
                'required' => false,
                'desc' => "Opcional. Debe utilizar la URL completa y debe ser HTTPS. Sólo configure su logo si es necesario que no se utilice el logo de su cuenta en Mobbex. Dimensiones: 250x250 píxeles. El Logo debe ser cuadrado para optimización.",
                'tab' => 'tab_appearence',
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Experiencia de Pago en el Sitio'),
                'name' => MobbexHelper::K_EMBED,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_general',
                'values' => [
                    [
                        'id' => 'active_on_embed',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_embed',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Mobbex Wallet para usuarios logeados'),
                'name' => MobbexHelper::K_WALLET,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_general',
                'values' => [
                    [
                        'id' => 'active_on_wallet',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_wallet',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type' => 'text',
                'label' => $this->l('ID o Clave de Revendedor'),
                'name' => MobbexHelper::K_RESELLER_ID,
                'required' => false,
                'tab' => 'tab_advanced',
                'desc' => "Ingrese este identificador sólo si se es parte de un programa de reventas. El identificador NO debe tener espacios, solo letras, números o guiones. El identificador se agregará a la referencia de Pago para identificar su venta.",
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Widget de financación en productos'),
                'desc' => $this->l('Mostrar el botón de financiación en la página del producto.'),
                'name' => MobbexHelper::K_PLANS,
                'is_bool' => true,
                'required' => true,
                'values' => [
                    [
                        'id' => 'active_on_plans',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_plans',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
                'tab' => 'tab_general',
            ],
            [
                'type'     => 'switch',
                'label'    => $this->l('Widget de financiación en carrito'),
                'desc'     => $this->l('Mostrar el botón de financiación en la página del carrito.'),
                'name'     => 'MOBBEX_PLANS_ON_CART',
                'is_bool'  => true,
                'required' => false,
                'values'   => [
                    [
                        'id' => 'active_on_plans_cart',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_plans_cart',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
                'tab' => 'tab_appearence',
            ],
            [
                'type' => 'text',
                'label' => $this->l('Imagen del botón de financiación ( URL )'),
                'name' => MobbexHelper::K_PLANS_IMAGE_URL,
                'required' => false,
                'desc' => $this->l('Opcional. Debe utilizar la URL completa y debe ser HTTPS.'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_IMAGE_URL,
            ],
            [
                'type' => 'text',
                'label' => $this->l('Plans Button Text'),
                'name' => MobbexHelper::K_PLANS_TEXT,
                'required' => false,
                'desc' => $this->l('Optional. Text displayed on finnancing button'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_TEXT,
            ],
            [
                'type' => 'textarea',
                'label' => $this->l('Finance widget styles'),
                'name' => MobbexHelper::K_PLANS_STYLES,
                'required' => false,
                'desc' => $this->l('Use the CSS sintaxys to give styles to your finance widget.'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_STYLES,
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Agregar campo DNI'),
                'name' => MobbexHelper::K_OWN_DNI,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_general',
                'values' => [
                    [
                        'id' => 'active_on_own_dni',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_own_dni',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Permite el uso de multiples tarjetas'),
                'name' => MobbexHelper::K_MULTICARD,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_multicard',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_multicard',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type'     => 'select',
                'label'    => $this->l('Multivendedor'),
                'desc'     => $this->l('Permite el uso de múltiples vendedores (hasta 4 entidades diferentes)'),
                'name'     => MobbexHelper::K_MULTIVENDOR,
                'required' => false,
                'tab'      => 'tab_advanced',
                'options'  => [
                    'query' => [
                        [
                            'id_option' => false,
                            'name'      => 'Desactivado'
                        ],
                        [
                            'id_option' => 'unified',
                            'name'      => 'Unificado'
                        ],
                        [
                            'id_option' => 'active',
                            'name'      => 'Activado'
                        ],
                    ],
                    'id'   => 'id_option',
                    'name' => 'name'
                ]
            ],
            [
                'type' => 'text',
                'label' => $this->l('Usar campo DNI existente'),
                'name' => MobbexHelper::K_CUSTOM_DNI,
                'required' => false,
                'tab' => 'tab_general',
                'desc' => "Si ya solicita el campo DNI al finalizar la compra o al registrarse, proporcione el nombre del campo personalizado.",
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Modo unificado'),
                'desc' => $this->l('Deshabilita la subdivisión de los métodos de pago en la página de finalización de la compra. Las opciones se verán dentro del checkout.'),
                'name' => MobbexHelper::K_UNIFIED_METHOD,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_unified_method',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_unified_method',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Modo Debug'),
                'name' => MobbexHelper::K_DEBUG,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_debug',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id' => 'active_off_debug',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => $this->l('Modo de pedidos priorizados'),
                'desc'     => $this->l('Los pedidos se crearán al momento de comenzar el pago con Mobbex en lugar de esperar la llegada del webhook.'),
                'name'     => MobbexHelper::K_ORDER_FIRST,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_advanced',
                'values'   => [
                    [
                        'id'    => 'active_on_order_first',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id'    => 'active_off_order_first',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => 'Cancelar pedidos pendientes luego de',
                'hint'     => 'Número de días en los que los pedidos se considerarán como pendientes de pago. Cumplido el plazo, estos quedarán cancelados y se devolverá el stock.',
                'name'     => 'MOBBEX_EXPIRATION_INTERVAL',
                'required' => false,
                'tab'      => 'tab_advanced',
                'col'      => 2,
                'suffix'   => 'días',
                'default'  => 3,
            ],
            [
                'type'     => 'switch',
                'label'    => 'Utilizar Cron para tareas programadas',
                'hint'     => 'Mejora el rendimiento del sitio separando la ejecución de las tareas programadas del módulo. Recuerde configurar el Cron Job del lado del servidor.',
                'desc'     => 'Una vez activada, configure su servidor para que ejecute el siguiente comando diariamente: <code>curl -s ' . \MobbexHelper::getModuleUrl('task') . '</code>',
                'name'     => 'MOBBEX_CRON_MODE',
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_advanced',
                'values'   => [
                    [
                        'id'    => 'active_on_cron_mode',
                        'value' => true,
                        'label' => $this->l('Activar'),
                    ],
                    [
                        'id'    => 'active_off_cron_mode',
                        'value' => false,
                        'label' => $this->l('Desactivar'),
                    ],
                ],
            ],
        ]
    ]
];