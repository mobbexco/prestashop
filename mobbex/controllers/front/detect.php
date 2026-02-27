<?php

use Mobbex\PS\Checkout\Models\Logger;
use Mobbex\PS\Checkout\Models\Config;

class MobbexDetectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        if ($this->module->active == false) {
            Logger::log(
                'fatal',
                '[Mobbex Transparent] Detect > postProcess | Controller Call On Module Inactive',
                $_REQUEST
            );
        }

        if (!Config::validateHash(Tools::getValue('hash'))) {
            Logger::log(
                'debug',
                '[Mobbex Transparent] Detect > postProcess | Hash could not be validated',
                $_REQUEST
            );
            return Tools::redirect('index.php?controller=order&step=3&typeReturn=failure');
        }

        try {
            $this->initContent();
        } catch (\Exception $e) {
            Logger::log(
                'debug',
                '[Mobbex Transparent] Detect > postProcess > Error on payment process',
                $e->getMessage()
            );
            Tools::redirect('index.php?controller=order&step=3&typeReturn=failure');
        }
    }

    public function initContent()
    {
        Logger::log(
            'debug', 
            '[Mobbex Transparent] initContent > init card detection', 
            $_REQUEST
        );
        parent::initContent();

        $bin   = Tools::getValue('bin');
        $token = Tools::getValue('token');

        if (!$bin || !$token) {
            Logger::log(
                'debug',
                '[Mobbex Transparent] Detect > initContent | Missing bin or token.',
                $_REQUEST
            );
            $this->ajaxDie(json_encode([
                'result' => false,
                'error' => 'Invalid BIN'
            ]));
        }

        try {
            $card = $this->detectCard($bin, $token);
            if (!$card)
                throw new \Exception(
                    '[Mobbex Transparent] Detect > card not found'
                );

            die(json_encode([
                'result' => true,
                'data'   => $card,
            ]));
        } catch (\Exception $e) {
            Logger::log(
                'debug',
                '[Mobbex Transparent] Detect > initContent | Detect card failed.',
                $e
            );
            die(json_encode([
                'result' => false,
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * Get installments and card brand from Mobbex API
     * 
     * @param string $bin Card BIN
     * @param string $token Mobbex Checkout intent token
     * 
     * @return array|false Installments data or false on error
     * 
     * @throws \Exception
     */
    private function detectCard($bin, $token)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "sources/detect/$token",
            'raw'    => true,
            'body'   => [
                'type' => 'card',
                'data' => ['bin' => $bin],
                'options' => [
                    'installments' => true,
                    'filter'       => null,
                    'brand'        => true,
                    'brands'       => true,
                    'multivendor'  => Config::$settings['multivendor'] ?: null,
                ],
            ],
        ]);

        if (empty($response['data']))
            throw new \Mobbex\Exception(sprintf(
                'Mobbex request error #%s: %s %s',
                isset($response['code']) ? $response['code'] : 'NOCODE',
                isset($response['error']) ? $response['error'] : 'NOERROR',
                isset($response['status_message']) ? $response['status_message'] : 'NOMESSAGE'
            ));

        return $response['data'];
    }
}
