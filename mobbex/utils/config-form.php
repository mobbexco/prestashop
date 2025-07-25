<?php

defined('_PS_VERSION_') || exit;

$widgetStyles ='
.mbbxWidgetOpenBtn {
    width: fit-content;
    min-height: 40px;
    border-radius: 6px;
    padding: 8px 18px; /* arriba/abajo, izquierda/derecha */
    font-size: 16px;
    color: #6f00ff;
    background-color: #ffffff;
    border: 2px solid #6f00ff; /* grosor, estilo de linea, color */
    cursor: pointer;
    /*box-shadow: 2px 2px 4px 0 rgba(0, 0, 0, .2);*/
}
.mbbxWidgetOpenBtn:hover {
    color: #ffffff;
    background-color: #6f00ff;
}';

/**
 * Module Configuration Input Form.
 * 
 * Input example:
 * {
 *  'type'     => string | input type <required>,
 *  'label'    => string | input label <required>,
 *  'name'     => string | input database name <required>,
 *  'key'      => string | snake case key to get config <required>,
 *  'default'  => mixed  | input default value <required>,
 *  'required' => bool   | if input is required, <optional>,
 *  'tab'      => string | name of the input father class <required>,
 *  'values'   => array  | array with options, only for select inputs <optional>,
 *  'desc'     => string | input description <optional>,
 *  'is_bool'  => bool   | if input is bool <optional>,
 *  'class'    => string | input class <optional>,
 * }
 * 
 * for 'type' => number is required  to set:
 *      'type'         => 'html',
 *      'html_content' => '<input type="number" name="inputName">' | attributes can be included here too,
 * 
 */
$form = [
    'form' => [
        'tabs' => [
            'tab_general'    => self::l('General', 'config-form'),
            'tab_orders'     => self::l('Orders Configuration', 'config-form'),
            'tab_appearence' => self::l('Appearance', 'config-form'),
            'tab_advanced'   => self::l('Advanced Configuration', 'config-form'),
        ],
        'legend' => [
            'title' => self::l('Settings', 'config-form'),
            'icon'  => 'icon-cogs',
        ],
        'submit' => [
            'title' => self::l('Save', 'config-form'),
        ],
        'success'     => null,
        'description' => null,
        'warning'     => null,
        'error'       => null,
        'input'       => [
            [
                'type'     => 'text',
                'label'    => self::l( 'API Key', 'config-form'),
                'name'     => 'MOBBEX_API_KEY',
                'key'      => 'api_key',
                'default'  => '',
                'required' => true,
                'tab'      => 'tab_general'
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Access Token', 'config-form'),
                'name'     => 'MOBBEX_ACCESS_TOKEN',
                'key'      => 'access_token',
                'default'  => '',
                'required' => true,
                'tab'      => 'tab_general'
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Test Mode', 'config-form'),
                'name'     => 'MOBBEX_TEST_MODE',
                'key'      => 'test',
                'default'  => false,
                'is_bool'  => true,
                'required' => true,
                'tab'      => 'tab_general',
                'values'   => [
                    [
                        'id'    => 'active_on_mdv',
                        'value' => true,
                        'label' => self::l('Test Mode', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_mdv',
                        'value' => false,
                        'label' => self::l('Live Mode', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Payment Method Title', 'config-form'),
                'name'     => 'MOBBEX_TITLE',
                'key'      => 'mobbex_title',
                'default'  => '',
                'required' => false,
                'desc'     => 'In chase of multiple payment sources, only "Debit/Credit card" method label will be replaced.',
                'tab'      => 'tab_appearence',
            ],
            [
                'type'     => 'switch',
                'label'    => self::l('Payment Method Icons', 'config-form'),
                'desc'     => "Shows methods icons in methods subdivision.",
                'name'     => 'MOBBEX_METHOD_ICON',
                'key'      => 'method_icon',
                'default'  => true,
                'is_bool'  => true,
                'required' => true,
                'tab'      => 'tab_appearence',
                'values'   => [
                    [
                        'id'    => 'active_icons_on',
                        'value' => true,
                        'label' => self::l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_icons_off',
                        'value' => false,
                        'label' => self::l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Payment Method Description', 'config-form'),
                'name'     => 'MOBBEX_DESCRIPTION',
                'key'      => 'mobbex_description',
                'default'  => '',
                'required' => false,
                'desc'     => 'In chase of multiple payment sources, the description will be displayed in "Debit/Credit card" method.',
                'tab'      => 'tab_appearence',
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Payment Method Image', 'config-form' ),
                'name'     => 'MOBBEX_PAYMENT_METHOD_IMAGE',
                'key'      => 'mobbex_payment_method_image',
                'default'  => '',
                'required' => false,
                'desc'     => 'Enter the url address from the image you want to use',
                'tab'      => 'tab_appearence',
            ],
            [
                'type'     => 'radio',
                'label'    => self::l( 'Theme Mode', 'config-form'),
                'name'     => 'MOBBEX_THEME',
                'key'      => 'theme',
                'default'  => 'light',
                'is_bool'  => false,
                'required' => false,
                'tab'      => 'tab_appearence',
                'values'   => [
                    [
                        'id'    => 'm_theme_light',
                        'value' => 'light',
                        'label' => self::l( 'Light Mode', 'config-form'),
                    ],
                    [
                        'id'    => 'm_theme_dark',
                        'value' => 'dark',
                        'label' => self::l( 'Dark Mode', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'color',
                'name'     => 'MOBBEX_THEME_BACKGROUND',
                'label'    => self::l( 'Background Color', 'config-form'),
                'key'      => 'background',
                'default'  => '#ECF2F6',
                'data-hex' => false,
                'class'    => 'mColorPicker',
                'desc'     => 'Checkout Background Color',
                'tab'      => 'tab_appearence',
            ],
            [
                'type'     => 'color',
                'label'    => self::l( 'Primary Color', 'config-form'),
                'name'     => 'MOBBEX_THEME_PRIMARY',
                'key'      => 'color',
                'default'  => '#6f00ff',
                'data-hex' => false,
                'class'    => 'mColorPicker',
                'desc'     => 'Checkout Primary Color',
                'tab'      => 'tab_appearence',
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Use Market configurated Logo', 'config-form'),
                'desc'     => "When is disabled, the logo configured in the Mobbex account will be used.",
                'name'     => 'MOBBEX_THEME_SHOP_LOGO',
                'key'      => 'shop_theme_logo',
                'default'  => false,
                'is_bool'  => true,
                'required' => true,
                'tab'      => 'tab_appearence',
                'values'   => [
                    [
                        'id'    => 'active_on_shop_logo',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_shop_logo',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Custom Logo ( URL )', 'config-form'),
                'name'     => 'MOBBEX_THEME_LOGO',
                'key'      => 'theme_logo',
                'default'  => '',
                'required' => false,
                'desc'     => 'Optional. You must use the full URL and it must be HTTPS. Only set your logo if you need to not use your Mobbex account logo. Dimensions: 250x250 pixels. The Logo must be square for optimization.',
                'tab'      => 'tab_appearence',
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Checkout Banner ( URL )', 'config-form'),
                'name'     => 'MOBBEX_CHECKOUT_BANNER',
                'key'      => 'checkout_banner',
                'default'  => '',
                'required' => false,
                'desc'     => 'Optional. You must use the full URL and it must be HTTPS. (default banner https://res.mobbex.com/ecommerce/mobbex_1.png )',
                'tab'      => 'tab_appearence',
            ],
            [
                'type'     => 'switch',
                'label'    => self::l('Embed Payment', 'config-form'),
                'name'     => 'MOBBEX_EMBED',
                'key'      => 'embed',
                'default'  => true,
                'is_bool'  => true,
                'required' => true,
                'tab'      => 'tab_general',
                'values'   => [
                    [
                        'id'    => 'active_on_embed',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_embed',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Mobbex Wallet for logged users', 'config-form'),
                'name'     => 'MOBBEX_WALLET',
                'key'      => 'wallet',
                'default'  => false,
                'is_bool'  => true,
                'required' => true,
                'tab'      => 'tab_general',
                'values'   => [
                    [
                        'id'    => 'active_on_wallet',
                        'value' => true,
                        'label' => self::l( 'Enabled', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_wallet',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'ID or Reseler Key', 'config-form'),
                'name'     => 'MOBBEX_RESELLER_ID',
                'key'      => 'reseller_id',
                'default'  => '',
                'required' => false,
                'tab'      => 'tab_advanced',
                'desc'     => "Ingrese este identificador sólo si se es parte de un programa de reventas. El identificador NO debe tener espacios, solo letras, números o guiones. El identificador se agregará a la referencia de Pago para identificar su venta.",
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Site Id', 'config-form'),
                'name'     => 'MOBBEX_SITE_ID',
                'key'      => 'site_id',
                'default'  => '',
                'required' => false,
                'tab'      => 'tab_advanced',
                'desc'     => "Si utiliza las mismas credenciales en otro sitio complete este campo con un identificador que permita diferenciar las referencias de sus operaciones. El identificador NO debe tener espacios, solo letras, números o guiones. El identificador se agregará a la referencia que se utiliza al crear el checkout.",
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Finance Widget in Product Page', 'config-form'),
                'desc'     => 'Show Finance Widget in Product Page.',
                'name'     => 'MOBBEX_PLANS',
                'key'      => 'finance_product',
                'default'  => false,
                'is_bool'  => true,
                'required' => true,
                'values'   => [
                    [
                        'id'    => 'active_on_plans',
                        'value' => true,
                        'label' => self::l( 'Enabled', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_plans',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
                'tab' => 'tab_general',
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Finance Widget on Cart', 'config-form'),
                'desc'     => 'Show the finance widget on cart page.',
                'name'     => 'MOBBEX_PLANS_ON_CART',
                'key'      => 'finance_cart',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_appearence',
                'values'   => [
                    [
                        'id'    => 'active_on_plans_cart',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_plans_cart',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Mostrar cuotas destacadas', 'config-form'),
                'name'     => 'MOBBEX_SHOW_FEATURED_INSTALLMENTS',
                'key'      => 'show_featured_installments',
                'default'  => true,
                'required' => false,
                'desc'     => 'Permite mostrar u ocultar las cuotas destacadas en el widget de financiación.',
                'tab'      => 'tab_appearence',
                'values'  => [
                    [
                        'id'    => 'show_featured_installments',
                        'value' => true,
                        'label' => self::l( 'Yes', 'config-form'),
                    ],
                    [
                        'id'    => 'hide_featured_installments',
                        'value' => false,
                        'label' => self::l( 'No', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Hide installments at checkout', 'config-form'),
                'name'     => 'MOBBEX_SHOW_NO_INTEREST_LABELS',
                'key'      => 'show_no_interest_labels',
                'default'  => true,
                'is_bool'  => true,
                'required' => false,
                'desc'     => 'Hide the "interest-free installments" labels in the checkout view.',
                'tab'      => 'tab_appearence',
                'values'  => [
                    [
                        'id'    => 'not_show_interest_labels',
                        'value' => true,
                        'label' => self::l( 'Yes', 'config-form'),
                    ],
                    [
                        'id'    => 'show_interest_labels',
                        'value' => false,
                        'label' => self::l( 'No', 'config-form'),
                    ],
                ],
            ],

            [
                'type'     => 'switch',
                'label'    => self::l( 'Add DNI Field', 'config-form'),
                'name'     => 'MOBBEX_OWN_DNI',
                'key'      => 'mobbex_dni',
                'default'  => true,
                'is_bool'  => true,
                'required' => true,
                'tab'      => 'tab_general',
                'values'  => [
                    [
                        'id'    => 'active_on_own_dni',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_own_dni',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Use Multicard in Checkout', 'config-form'),
                'name'     => 'MOBBEX_MULTICARD',
                'key'      => 'multicard',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_advanced',
                'values'   => [
                    [
                        'id' => 'active_on_multicard',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_multicard',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => self::l('Conversión de Moneda', 'config-form'),
                'name'     => 'MOBBEX_FINAL_CURRENCY',
                'key'      => 'final_currency',
                'default'  => '',
                'required' => false,
                'tab'      => 'tab_advanced',
                'desc'     => 'Permite procesar la transacción en una moneda diferente a la que se muestra en la tienda. Ej. ARS, USD.',
            ],
            [
                'type'     => 'switch',
                'label'    => self::l('Enable/disable finance charge discount (BETA)', 'config-form'),
                'name'     => 'MOBBEX_DISCOUNT',
                'key'      => 'charge_discount',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_advanced',
                'values'   => [
                    [
                        'id' => 'active_on_discount',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id' => 'active_off_discount',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'select',
                'label'    => self::l( 'Multivendor', 'config-form'),
                'desc'     => 'Allow th use of multiple vendor (4 diferent entities supported)',
                'name'     => 'MOBBEX_MULTIVENDOR',
                'key'      => 'multivendor',
                'default'  => '',
                'required' => false,
                'tab'      => 'tab_advanced',
                'options'  => [
                    'query' => [
                        [
                            'id_option' => '',
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
                'type'     => 'text',
                'label'    => self::l( 'Use Existing DNI Field', 'config-form'),
                'name'     => 'MOBBEX_CUSTOM_DNI',
                'key'      => 'custom_dni',
                'default'  => '',
                'required' => false,
                'tab'      => 'tab_general',
                'desc'     => "Si ya solicita el campo DNI al finalizar la compra o al registrarse, proporcione el nombre del campo personalizado.<br>
                (Debe ser escrito respetando el siguiente formato: 'table:column_identifier:column').",
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Medios de pago en sitio', 'config-form'),
                'desc'     => 'Habilita la subdivisión de los métodos de pago en la página de finalización de la compra.',
                'name'     => 'MOBBEX_PAYMENT_METHODS',
                'key'      => 'payment_methods',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_advanced',
                'values'   => [
                    [
                        'id'    => 'active_on_payment_methods',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_payment_methods',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Debug Mode', 'config-form'),
                'name'     => 'MOBBEX_DEBUG',
                'key'      => 'debug_mode',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_advanced',
                'values'   => [
                    [
                        'id'    => 'active_on_debug',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_debug',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Order when Processing Payment', 'config-form'),
                'desc'     => 'The order will be created just before starting the payment instead of when receiving the webhook.',
                'name'     => 'MOBBEX_ORDER_FIRST',
                'key'      => 'order_first',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_orders',
                'values'   => [
                    [
                        'id'    => 'active_on_order_first',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_order_first',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Discount stock in pending orders', 'config-form'),
                'desc'     => 'Discount product stock in pending orders',
                'name'     => 'MOBBEX_PENDING_ORDER_DISCOUNT',
                'key'      => 'pending_discount',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_orders',
                'values'   => [
                    [
                        'id'    => 'active_on_discount_stock_pending',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_discount_stock_pending',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'switch',
                'label'    => self::l( 'Cart restoration in Order First Mode', 'config-form'),
                'desc'     => 'The customer cart will be restored if checkout is closed, it cancell the order and creates new one.',
                'name'     => 'MOBBEX_CART_RESTORE',
                'key'      => 'cart_restore',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_orders',
                'values'   => [
                    [
                        'id'    => 'active_on_cart_restore',
                        'value' => true,
                        'label' => self::l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_cart_restore',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'select',
                'label'    => self::l('Payment Mode', 'config-form'),
                'desc'     => 'El modo de pago en dos pasos asigna el estado autorizado al pedido y permite capturar el pedido en el panel de administración del pedido. En Two Steps Mode, los pedidos pasaran a esatdo autorizado',
                'name'     => 'MOBBEX_PAYMENT_MODE',
                'key'      => 'payment_mode',
                'default'  => 'payment.v2',
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => [
                        [
                            'id_option' => 'payment.v2',
                            'name'      => 'Payment v2'
                        ],
                        [
                            'id_option' => 'payment.2-step',
                            'name'      => 'Two Step Payment'
                        ],
                    ],
                    'id'   => 'id_option',
                    'name' => 'name'
                ]
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Cancelar pedidos pendientes luego de', 'config-form'),
                'hint'     => 'Tiempo en el que los pedidos se considerarán como pendientes de pago. Cumplido el plazo, estos quedarán cancelados y se devolverá el stock.',
                'name'     => 'MOBBEX_EXPIRATION_INTERVAL',
                'key'      => 'expiration_interval',
                'default'  => 3,
                'required' => false,
                'tab'      => 'tab_orders',
                'col'      => 2,
            ],
            [
                'type'      => 'select',
                'name'      => 'MOBBEX_EXPIRATION_PERIOD',
                'desc'      => 'Recuerde utilizar un Cron Job para períodos inferiores a 24 horas',
                'required'  => false,
                'key'       => 'expiration_period',
                'default'   => 'day',
                'tab'       => 'tab_orders',
                'options'   => [
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
                'label'    => self::l( 'Utilizar Cron para tareas programadas', 'config-form'),
                'hint'     => 'Mejora el rendimiento del sitio separando la ejecución de las tareas programadas del módulo. Recuerde configurar el Cron Job del lado del servidor.',
                'desc'     => 'Una vez activada, configure su servidor para que ejecute el siguiente comando diariamente: <code>curl -s ' . \Mobbex\PS\Checkout\Models\OrderHelper::getModuleUrl('task') . '</code>',
                'name'     => 'MOBBEX_CRON_MODE',
                'key'      => 'cron_mode',
                'default'  => false,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_advanced',
                'values'   => [
                    [
                        'id'    => 'active_on_cron_mode',
                        'value' => true,
                        'label' => self::l( 'Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_cron_mode',
                        'value' => false,
                        'label' => self::l( 'Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'text',
                'label'    => self::l( 'Tiempo de espera en redireccion', 'config-form'),
                'hint'     => 'Este tiempo se utilizará sólo si el pedido no existe al redirijir. Recuerde configurar su servidor para que el tiempo no exceda el límite definido en <code>max_execution_time</code>',
                'desc'     => 'Duración máxima de tiempo que se esperará para redirijir a la página de confirmación del pedido una vez se realiza el pago',
                'name'     => 'MOBBEX_REDIRECT_TIME',
                'key'      => 'redirect_time',
                'default'  => 10,
                'required' => false,
                'tab'      => 'tab_advanced',
                'col'      => 3,
                'suffix'   => 'segundos',
            ],
            [
                'type'    => 'text',
                'label'   => self::l ( 'Force asset load in', 'config-form'),
                'hint'    => 'Directly print the elements that link the plugin assets in the specified view, bypassing the PrestaShop method.',
                'name'    => 'MOBBEX_FORCE_ASSETS',
                'key'     => 'force_assets',
                'tab'     => 'tab_advanced',
                'default' => '',
            ],
            [
                'type'    => 'text',
                'label'   => self::l ( 'Timeout', 'config-form'),
                'hint'    => 'Set the lifetime of the Mobbex checkout once generated',
                'desc'    => 'Establece el tiempo de vida del checkout una vez generado',
                'name'    => 'MOBBEX_TIMEOUT',
                'key'     => 'timeout',
                'tab'     => 'tab_advanced',
                'default' => 5,
                'col'      => 3,
                'suffix'   => 'minutos',
            ],
            [
                'type'    => 'switch',
                'label'   => self::l('Procesar Reintentos de Webhooks', 'config-form'),
                'hint'    => 'Los reintentos de webhooks se procesarán y modificarán al pedido. Si se desactiva sólo se guardarán en la base de datos.',
                'name'    => 'MOBBEX_PROCESS_WEBHOOK_RETRIES',
                'key'     => 'process_webhook_retries',
                'tab'     => 'tab_advanced',
                'default' => true,
                'is_bool' => true,
                'values'  => [
                    [
                        'id'    => 'active_on_process_webhook_retries',
                        'value' => true,
                        'label' => self::l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_process_webhook_retries',
                        'value' => false,
                        'label' => self::l('Disabled', 'config-form'),
                    ],
                ],
            ],
            [
                'type'     => 'select',
                'label'    => self::l( 'Order Status Approved', 'config-form'),
                'desc'     => 'Select the status for approved orders.',
                'name'     => 'MOBBEX_ORDER_STATUS_APPROVED',
                'key'      => 'order_status_approved',
                'default'  => \Configuration::get('PS_OS_PAYMENT'),
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => \OrderState::getOrderStates(\Context::getContext()->language->id) ?: [],
                    'id'    => 'id_order_state',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'select',
                'label'    => self::l( 'Order Status Authorized', 'config-form'),
                'desc'     => 'Select the status for authorized orders.',
                'name'     => 'MOBBEX_ORDER_STATUS_AUTHORIZED',
                'key'      => 'order_status_authorized',
                'default'  => \Configuration::get('MOBBEX_OS_AUTHORIZED'),
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => \OrderState::getOrderStates(\Context::getContext()->language->id) ?: [],
                    'id'    => 'id_order_state',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'select',
                'label'    => self::l( 'Order Status Failed', 'config-form'),
                'desc'     => 'Select the status for approved orders.',
                'name'     => 'MOBBEX_ORDER_STATUS_FAILED',
                'key'      => 'order_status_failed',
                'default'  => \Configuration::get('PS_OS_ERROR'),
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => \OrderState::getOrderStates(\Context::getContext()->language->id) ?: [],
                    'id'    => 'id_order_state',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'select',
                'label'    => self::l( 'Order Status Rejected', 'config-form'),
                'desc'     => 'Select the status for rejected orders.',
                'name'     => 'MOBBEX_ORDER_STATUS_REJECTED',
                'key'      => 'order_status_rejected',
                'default'  => \Configuration::get('PS_OS_ERROR'),
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => \OrderState::getOrderStates(\Context::getContext()->language->id) ?: [],
                    'id'    => 'id_order_state',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'select',
                'label'    => self::l( 'Order Status Refunded', 'config-form'),
                'desc'     => 'Select the status for refunded orders.',
                'name'     => 'MOBBEX_ORDER_STATUS_REFUNDED',
                'key'      => 'order_status_refunded',
                'default'  => \Configuration::get('PS_OS_REFUND'),
                'required' => false,
                'tab'      => 'tab_orders',
                'options'  => [
                    'query' => \OrderState::getOrderStates(\Context::getContext()->language->id) ?: [],
                    'id'    => 'id_order_state',
                    'name'  => 'name'
                ]
            ],
            [
                'type'     => 'switch',
                'label'    => self::l('Verificación de Totales', 'config-form'),
                'hint'     => 'Esta verificación es escencial para asegurar que el pedido se genere correctamente, su funcionamiento es el predeterminado en el plugin desde enero de 2023.',
                'desc'     => 'Por defecto se encuentra activa. Realiza una verificación para asegurar que los pedidos se realizan por el mismo monto que el checkout de Mobbex. Advertencia! Desactivar esta opción es sólo recomendable en casos muy particulares.',
                'name'     => 'MOBBEX_CHECK_CART_TOTALS',
                'key'      => 'check_cart_totals',
                'default'  => true,
                'is_bool'  => true,
                'required' => false,
                'tab'      => 'tab_orders',
                'values'   => [
                    [
                        'id'    => 'active_on_check_cart_totals',
                        'value' => true,
                        'label' => self::l('Enable', 'config-form'),
                    ],
                    [
                        'id'    => 'active_off_check_cart_totals',
                        'value' => false,
                        'label' => self::l('Disabled', 'config-form'),
                    ],
                ],
            ],
        ]
    ]
];

return $form;
