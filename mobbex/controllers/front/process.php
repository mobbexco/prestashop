<?php

use Mobbex\PS\Checkout\Models\Logger;
use Mobbex\PS\Checkout\Models\Config;
use Mobbex\PS\Checkout\Models\OrderHelper;

class MobbexProcessModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($this->module->active == false) {
            Logger::log(
                'fatal',
                '[Mobbex Transparent] Process > postProcess | Controller Call On Module Inactive',
                $_REQUEST
            );
        }

        if (!Config::validateHash(Tools::getValue('hash'))) {
            Logger::log(
                'debug',
                '[Mobbex Transparent] Process > postProcess | Hash could not be validated',
                $_REQUEST
            );
            return Tools::redirect('index.php?controller=order&step=3&typeReturn=failure');
        }

        try {
            $order = Config::$settings['order_first'] 
                ? $this->module->helper->processOrder($this->module)
                : true;
            if (!$order)
                throw new \Mobbex\Exception('Error on order creation.');

            $result = $this->process();

            $context       = Context::getContext();
            $cartId        = isset($context->cart->id) ? (int) $context->cart->id : 0;
            $customerId    = isset($context->customer->id) ? (int) $context->customer->id : 0;
            $statusCode    = !empty($result['status']['code']) ? $result['status']['code'] : 0;
            $transactionId = !empty($result['id']) ? $result['id'] : (!empty($result['payment']['id']) ? $result['payment']['id'] : '');

            $returnUrl = OrderHelper::getModuleUrl(
                'notification',
                'return',
                "&id_cart=$cartId&customer_id=$customerId&transactionId=$transactionId&status=$statusCode"
            );
            Tools::redirect($returnUrl);
        } catch (\Exception $e) {
            Logger::log(
                'debug',
                '[Mobbex Transparent] Process > postProcess > Error on payment process',
                $e->getMessage()
            );
            Tools::redirect('index.php?controller=order&step=3&typeReturn=failure');
        }
    }

    /**
     * Create a transparent checkout and process the order if needed.
     * 
     * @return array
     */
    public function process()
    {
        Logger::log(
            'debug',
            '[Mobbex Transparent] Process > init payment process',
            $_REQUEST
        );

        $postData = $this->getRequestData();
        $this->validateBody($postData);

        $checkout = (new OrderHelper())->getPaymentData();
        if (empty($checkout) || empty($checkout['intent']['token'])) {
            throw new \Exception('Error on checkout creation.');
        }

        $card = $this->cardToken(
            $checkout['intent']['token'],
            $postData['number'],
            $postData['expiry'],
            $postData['cvv'],
            $postData['name'],
            $postData['identification']
        );

        if (empty($card['token'])) {
            throw new \Mobbex\Exception('Error on token creation');
        }

        $res = $this->processOperation(
            $checkout['intent']['token'],
            $card['token'],
            $postData['installments']
        );

        if (empty($res) || empty($res['status']['code'])) {
            throw new \Mobbex\Exception('Error on operation process. Empty response', 0, $res);
        }

        if (!in_array($res['status']['code'], ['3', '100', '200'])) {
            throw new \Mobbex\Exception('Operation process with error code', 0, $res);
        }

        Logger::log('debug', '[Mobbex Transparent] Process > success payment process');

        return $res;
    }
    /**
     * Parse JSON or form-urlencoded payload.
     *
     * @return array
     */
    private function getRequestData()
    {
        $jsonData = json_decode(Tools::file_get_contents('php://input'), true);

        if (is_array($jsonData) && !empty($jsonData)) {
            return $jsonData;
        }

        return [
            'cvv'            => Tools::getValue('cvv'),
            'name'           => Tools::getValue('name'),
            'expiry'         => Tools::getValue('expiry'),
            'number'         => Tools::getValue('number'),
            'installments'   => Tools::getValue('installments'),
            'identification' => Tools::getValue('identification'),
        ];
    }

    /**
     * Validate request body
     *
     * @param array $body Request body
     *
     * @throws \Exception
     */
    private function validateBody($body)
    {
        if (empty($body) || !is_array($body)) {
            throw new \Exception('Invalid request body.');
        }

        $cvv            = isset($body['cvv']) ? $body['cvv'] : null;
        $name           = isset($body['name']) ? $body['name'] : null;
        $number         = isset($body['number']) ? $body['number'] : null;
        $expiry         = isset($body['expiry']) ? $body['expiry'] : null;
        $installments   = isset($body['installments']) ? $body['installments'] : null;
        $identification = isset($body['identification']) ? $body['identification'] : null;

        if (!$number || !$expiry || !$cvv || !$name || !$identification || !$installments) {
            throw new \Exception('Missing required fields.');
        }

        if (!is_string($number) || !is_string($expiry) || !is_string($cvv) || !is_string($name) || !is_string($identification) || !is_string($installments)) {
            throw new \Exception('All fields must be strings.');
        }

        if (strlen($number) < 15 || strlen($number) > 19) {
            throw new \Exception('Number must be at least 15 and not more than 19 characters long.');
        }

        if (!preg_match('/^[0-9]+$/', $number)) {
            throw new \Exception('Number must contain only numbers.');
        }

        if (strlen($expiry) < 4 || strlen($expiry) > 5) {
            throw new \Exception('Expiry must be at least 4 and not more than 5 characters long.');
        }

        if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $expiry)) {
            throw new \Exception('Expiry must be in MM/YY format.');
        }

        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            throw new \Exception('CVV must be at least 3 and not more than 4 characters long.');
        }

        if (!preg_match('/^[0-9]+$/', $cvv)) {
            throw new \Exception('CVV must contain only numbers.');
        }

        if (strlen($name) < 3 || strlen($name) > 50) {
            throw new \Exception('Name must be at least 3 and not more than 50 characters long.');
        }

        if (strlen($identification) < 7 || strlen($identification) > 15) {
            throw new \Exception('Identification must be between 7 and 15 characters long.');
        }

        if (!preg_match('/^[0-9]+$/', $identification)) {
            throw new \Exception('Identification must contain only numbers.');
        }
    }

    /**
     * Tokenize card number with Mobbex API.
     *
     * @param string $intentToken Checkout token
     * @param string $number Card number
     * @param string $expiry Card expiry in MM/YY format
     * @param string $cvv Card CVV
     * @param string $name Card holder name
     * @param string $identification Card holder identification
     *
     * @return array Token response data.
     *
     * @throws \Exception
     */
    private function cardToken($intentToken, $number, $expiry, $cvv, $name, $identification)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "sources/token/$intentToken",
            'raw'    => true,
            'body'   => [
                'source' => [
                    'card' => [
                        'cvv'            => $cvv,
                        'name'           => $name,
                        'number'         => $number,
                        'identification' => $identification,
                        'month'          => explode('/', $expiry)[0],
                        'year'           => explode('/', $expiry)[1],
                    ],
                ],
            ]
        ]);

        if (empty($response['data'])) {
            throw new \Mobbex\Exception(sprintf(
                'Mobbex request error #%s: %s %s',
                isset($response['code']) ? $response['code'] : 'NOCODE',
                isset($response['error']) ? $response['error'] : 'NOERROR',
                isset($response['status_message']) ? $response['status_message'] : 'NOMESSAGE'
            ));
        }

        return $response['data'];
    }

    /**
     * Process payment operation.
     *
     * @param string $intentToken Checkout token
     * @param string $cardToken Card token
     * @param string $installment Installment number (reference)
     *
     * @return array Process response data.
     *
     * @throws \Exception
     */
    public function processOperation($intentToken, $cardToken, $installment)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "operations/$intentToken",
            'raw'    => true,
            'body'   => [
                'source'      => $cardToken,
                'installment' => $installment,
            ]
        ]);

        if (empty($response['data'])) {
            throw new \Mobbex\Exception(sprintf(
                'Mobbex request error #%s: %s %s',
                isset($response['code']) ? $response['code'] : 'NOCODE',
                isset($response['error']) ? $response['error'] : 'NOERROR',
                isset($response['status_message']) ? $response['status_message'] : 'NOMESSAGE'
            ));
        }

        return $response['data'];
    }
}
