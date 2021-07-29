<?php
/**
 * wallet.php
 *
 * Main file of the module
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 2.3.0
 * @see     PaymentModuleCore
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * This front controller builds the payment request and then redirects the
 * customer to a new checkout step with wallet information. It is only
 * used in ps 1.6 when wallet is active.
 */
class MobbexWalletModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	public $display_column_left = false;

	public function initContent()
	{
		parent::initContent();
		
		$cart = $this->context->cart;
		$customer = new Customer($cart->id_customer);
		$is_wallet = (Configuration::get(MobbexHelper::K_WALLET) && Context::getContext()->customer->isLogged());

		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || !Validate::isLoadedObject($customer) || empty($cart->nbProducts()) || !$is_wallet) {
			Tools::redirect('index.php?controller=order&step=1');
		}

		$authorized = false;
		foreach (Module::getPaymentModules() as $module) {

			if ($module['name'] == 'mobbex') {
				$authorized = true;
				break;
			}

		}
		if (!$authorized) {
			die($this->module->l('This payment method is not available.', 'validation'));
		}
		
        $payment_data = MobbexHelper::getPaymentData();

		$this->context->smarty->assign(array(
			'wallet' => !empty($payment_data['wallet']) ? json_encode($payment_data['wallet']) : null,
			'is_wallet' => $is_wallet,
			'checkout_id' => $payment_data['id'],
			'checkout_url' => $payment_data['return_url'],
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'path_module' => $this->module->getPathUri(),
			'js_url' => Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex/views/js/front.js'),
			'css_url' => Media::getMediaPath(_PS_MODULE_DIR_ . 'mobbex/views/css/front.css'),
		));

		$this->setTemplate('payment_execution.tpl');
	}
}