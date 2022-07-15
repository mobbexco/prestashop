<?php

defined('_PS_VERSION_') || exit;

return [
    'form' => [
        'tabs'        => [
            'tab_general'    => $this->l('General', 'config-form'),
            'tab_orders'    => $this->l('Orders Configuration', 'config-form'),
            'tab_appearence' => $this->l('Appearance', 'config-form'),
            'tab_advanced'   => $this->l('Advanced Configuration', 'config-form'),
        ],
        'legend'      => [
            'title' => $this->l('Settings', 'config-form'),
            'icon'  => 'icon-cogs',
        ],
        'submit'      => [
            'title' => $this->l('Save', 'config-form'),
        ],
        'success'     => null,
        'description' => null,
        'warning'     => null,
        'error'       => null,
        'input'       => [
            [
                'type' => 'text',
                'label' => $this->l('API Key', 'config-form'),
                'name' => MobbexHelper::K_API_KEY,
                'required' => true,
                'tab' => 'tab_general'
            ],
            [
                'type' => 'text',
                'label' => $this->l('Access Token', 'config-form'),
                'name' => MobbexHelper::K_ACCESS_TOKEN,
                'required' => true,
                'tab' => 'tab_general'
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Test Mode', 'config-form'),
                'name' => MobbexHelper::K_TEST_MODE,
                'is_bool' => true,
                'required' => true,
                'values' => [
                    [
                        'id' => 'active_on_mdv',
                        'value' => true,
                        'label' => $this->l('Test Mode', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_mdv',
                        'value' => false,
                        'label' => $this->l('Live Mode', 'config-form'),
                    ],
                ],
                'tab' => 'tab_general'
            ],
            [
                'type'     => 'text',
                'label'    => $this->l('Payment Method Title', 'config-form'),
                'name'     => 'MOBBEX_TITLE',
                'required' => false,
                'desc'     => $this->l('In chase of multiple payment sources, only "Debit/Credit card" method label will be replaced.', 'config-form'),
                'tab'      => 'tab_appearence',
                'default'  => '',
            ],
            [
                'type'     => 'text',
                'label'    => $this->l('Payment Method Description', 'config-form'),
                'name'     => 'MOBBEX_DESCRIPTION',
                'required' => false,
                'desc'     => $this->l('In chase of multiple payment sources, the description will be displayed in "Debit/Credit card" method.', 'config-form'),
                'tab'      => 'tab_appearence',
                'default'  => '',
            ],
            [
                'type' => 'radio',
                'label' => $this->l('Theme Mode', 'config-form'),
                'name' => MobbexHelper::K_THEME,
                'is_bool' => false,
                'required' => false,
                'tab' => 'tab_appearence',
                'values' => [
                    [
                        'id' => 'm_theme_light',
                        'value' => MobbexHelper::K_THEME_LIGHT,
                        'label' => $this->l('Light Mode', 'config-form'),
                    ],
                    [
                        'id' => 'm_theme_dark',
                        'value' => MobbexHelper::K_THEME_DARK,
                        'label' => $this->l('Dark Mode', 'config-form'),
                    ],
                ],
                'default' => MobbexHelper::K_DEF_THEME,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Background Color', 'config-form'),
                'name' => MobbexHelper::K_THEME_BACKGROUND,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Checkout Background Color', 'config-form'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_BACKGROUND,
            ],
            [
                'type' => 'color',
                'label' => $this->l('Primary Color', 'config-form'),
                'name' => MobbexHelper::K_THEME_PRIMARY,
                'data-hex' => false,
                'class' => 'mColorPicker',
                'desc' => $this->l('Checkout Primary Color', 'config-form'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PRIMARY,
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Use Market configurated Logo', 'config-form'),
                'desc' => $this->l("When is disabled, the logo configured in the Mobbex account will be used.", 'config-form'),
                'name' => MobbexHelper::K_THEME_SHOP_LOGO,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_appearence',
                'values' => [
                    [
                        'id' => 'active_on_shop_logo',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_shop_logo',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type' => 'text',
                'label' => $this->l('Custom Logo ( URL )', 'config-form'),
                'name' => MobbexHelper::K_THEME_LOGO,
                'required' => false,
                'desc' => $this->l('Optional. You must use the full URL and it must be HTTPS. Only set your logo if you need to not use your Mobbex account logo. Dimensions: 250x250 pixels. The Logo must be square for optimization.', 'config-form'),
                'tab' => 'tab_appearence',
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Embed Payment', 'config-form'),
                'name' => MobbexHelper::K_EMBED,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_general',
                'values' => [
                    [
                        'id' => 'active_on_embed',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_embed',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Mobbex Wallet for logged users', 'config-form'),
                'name' => MobbexHelper::K_WALLET,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_general',
                'values' => [
                    [
                        'id' => 'active_on_wallet',
                        'value' => true,
                        'label' => $this->l('Enabled', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_wallet',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type' => 'text',
                'label' => $this->l('ID or Reseler Key', 'config-form'),
                'name' => MobbexHelper::K_RESELLER_ID,
                'required' => false,
                'tab' => 'tab_advanced',
                'desc' => "Ingrese este identificador sólo si se es parte de un programa de reventas. El identificador NO debe tener espacios, solo letras, números o guiones. El identificador se agregará a la referencia de Pago para identificar su venta.",
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Finance Widget in Product Page', 'config-form'),
                'desc' => $this->l('Show Finance Widget in Product Page.', 'config-form'),
                'name' => MobbexHelper::K_PLANS,
                'is_bool' => true,
                'required' => true,
                'values' => [
                    [
                        'id' => 'active_on_plans',
                        'value' => true,
                        'label' => $this->l('Enabled', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_plans',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
                'tab' => 'tab_general',
            ],
            [
                'type'     => 'switch',
                'label'    => $this->l('Iframe Finance Widget', 'config-form'),
                'desc'     => $this->l('Render the finance widget using the same view used inside the checkout.', 'config-form'),
                'name'     => 'MOBBEX_PLANS_IFRAME',
                'is_bool'  => true,
                'required' => false,
                'default'  => false,
                'values'   => [
                    [
                        'id'    => 'active_on_plans_iframe',
                        'value' => true,
                        'label' => $this->l('Enabled', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_plans_iframe',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
                'tab' => 'tab_appearence',
            ],
            [
                'type'     => 'switch',
                'label'    => $this->l('Finance Widget on Cart', 'config-form'),
                'desc'     => $this->l('Show the finance widget on cart page.', 'config-form'),
                'name'     => 'MOBBEX_PLANS_ON_CART',
                'is_bool'  => true,
                'required' => false,
                'values'   => [
                    [
                        'id' => 'active_on_plans_cart',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_plans_cart',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
                'tab' => 'tab_appearence',
            ],
            [
                'type' => 'text',
                'label' => $this->l('Finance Button Image ( URL )', 'config-form'),
                'name' => MobbexHelper::K_PLANS_IMAGE_URL,
                'required' => false,
                'desc' => $this->l('Opcional. Debe utilizar la URL completa y debe ser HTTPS.'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_IMAGE_URL,
            ],
            [
                'type' => 'text',
                'label' => $this->l('Plans Button Text', 'config-form'),
                'name' => MobbexHelper::K_PLANS_TEXT,
                'required' => false,
                'desc' => $this->l('Optional. Text displayed on finnancing button', 'config-form'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_TEXT,
            ],
            [
                'type' => 'textarea',
                'label' => $this->l('Finance widget styles', 'config-form'),
                'name' => MobbexHelper::K_PLANS_STYLES,
                'required' => false,
                'desc' => $this->l('Use the CSS syntaxis to give styles to your finance widget.', 'config-form'),
                'tab' => 'tab_appearence',
                'default' => MobbexHelper::K_DEF_PLANS_STYLES,
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Add DNI Field', 'config-form'),
                'name' => MobbexHelper::K_OWN_DNI,
                'is_bool' => true,
                'required' => true,
                'tab' => 'tab_general',
                'values' => [
                    [
                        'id' => 'active_on_own_dni',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_own_dni',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Use Multicard in Checkout', 'config-form'),
                'name' => MobbexHelper::K_MULTICARD,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_multicard',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_multicard',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'select',
                'label'    => $this->l('Multivendor'),
                'desc'     => $this->l('Allow th use of multiple vendor (4 diferent entities supported)'),
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
                'label' => $this->l('Use Existing DNI Field'),
                'name' => MobbexHelper::K_CUSTOM_DNI,
                'required' => false,
                'tab' => 'tab_general',
                'desc' => "Si ya solicita el campo DNI al finalizar la compra o al registrarse, proporcione el nombre del campo personalizado.",
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Unified Mode'),
                'desc' => $this->l('Disables subdivision of payment methods on the checkout page. The options will be seen within the checkout.', 'config-form'),
                'name' => MobbexHelper::K_UNIFIED_METHOD,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_unified_method',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_unified_method',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type' => 'switch',
                'label' => $this->l('Debug Mode', 'config-form'),
                'name' => MobbexHelper::K_DEBUG,
                'is_bool' => true,
                'required' => false,
                'tab' => 'tab_advanced',
                'values' => [
                    [
                        'id' => 'active_on_debug',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_debug',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => $this->l('Oreder when Processing Payment', 'config-form'),
                'desc'     => $this->l('The order will be created just before starting the payment instead of when receiving the webhook.', 'config-form'),
                'name'     => MobbexHelper::K_ORDER_FIRST,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_orders',
                'values'   => [
                    [
                        'id'    => 'active_on_order_first',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_order_first',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => $this->l('Discount stock in pending orders', 'config-form'),
                'desc'     => $this->l('Discount product stock in pending orders', 'config-form'),
                'name'     => MobbexHelper::K_PENDING_ORDER_DISCOUNT,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_orders',
                'values'   => [
                    [
                        'id'    => 'active_on_order_first',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_order_first',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => 'Cancelar pedidos pendientes luego de',
                'hint'     => 'Tiempo en el que los pedidos se considerarán como pendientes de pago. Cumplido el plazo, estos quedarán cancelados y se devolverá el stock.',
                'name'     => 'MOBBEX_EXPIRATION_INTERVAL',
                'required' => false,
                'tab'      => 'tab_orders',
                'col'      => 2,
                'default'  => 3,
            ],
            [
                'type'     => 'select',
                'name'     => 'MOBBEX_EXPIRATION_PERIOD',
                'desc'     => 'Recuerde utilizar un Cron Job para períodos inferiores a 24 horas',
                'required' => false,
                'tab'      => 'tab_orders',
                'default'  => 'day',
                'options'  => [
                    'id'    => 'id_option',
                    'name'  => 'name',
                    'query' => [
                        [
                            'id_option' => 'minute',
                            'name'      => 'minutos',
                        ],
                        [
                            'id_option' => 'hour',
                            'name'      => 'horas',
                        ],
                        [
                            'id_option' => 'day',
                            'name'      => 'días',
                        ],
                    ],
                ],
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
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_cron_mode',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => 'Tiempo de espera en redireccion',
                'hint'     => 'Este tiempo se utilizará sólo si el pedido no existe al redirijir. Recuerde configurar su servidor para que el tiempo no exceda el límite definido en <code>max_execution_time</code>',
                'desc'     => 'Duración máxima de tiempo que se esperará para redirijir a la página de confirmación del pedido una vez se realiza el pago',
                'name'     => 'MOBBEX_REDIRECT_TIME',
                'required' => false,
                'tab'      => 'tab_advanced',
                'default'  => 10,
                'col'      => 3,
                'suffix'   => 'segundos',
            ],
            [
                'type'    => 'switch',
                'label'   => $this->l('Force asset load', 'config-form'),
                'hint'    => $this->l('Directly print the elements that link the plugin assets, bypassing the PrestaShop method.', 'config-form'),
                'name'    => 'MOBBEX_FORCE_ASSETS',
                'tab'     => 'tab_advanced',
                'is_bool' => true,
                'default' => false,
                'values'  => [
                    [
                        'id'    => 'active_on_force_assets',
                        'value' => true,
                        'label' => $this->l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_force_assets',
                        'value' => false,
                        'label' => $this->l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'select',
                'label'    => $this->l('Order Status Approved', 'config-form'),
                'desc'     => $this->l('Select the status for approved orders.', 'config-form'),
                'name'     => MobbexHelper::K_ORDER_STATUS_APPROVED,
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => MobbexHelper::getOrderStatusSelect(),
                    'id'    => 'id_option',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'select',
                'label'    => $this->l('Order Status Failed', 'config-form'),
                'desc'     => $this->l('Select the status for approved orders.', 'config-form'),
                'name'     => MobbexHelper::K_ORDER_STATUS_FAILED,
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => MobbexHelper::getOrderStatusSelect(),
                    'id'    => 'id_option',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'select',
                'label'    => $this->l('Order Status Rejected', 'config-form'),
                'desc'     => $this->l('Select the status for rejected orders.', 'config-form'),
                'name'     => MobbexHelper::K_ORDER_STATUS_REJECTED,
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => MobbexHelper::getOrderStatusSelect(),
                    'id'    => 'id_option',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'select',
                'label'    => $this->l('Order Status Refunded', 'config-form'),
                'desc'     => $this->l('Select the status for refunded orders.', 'config-form'),
                'name'     => MobbexHelper::K_ORDER_STATUS_REFUNDED,
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => MobbexHelper::getOrderStatusSelect(),
                    'id'    => 'id_option',
                    'name'  => 'name'
                ]
            ],
        ]
    ]
];
