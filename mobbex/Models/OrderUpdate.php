<?php

namespace Mobbex\PS\Checkout\Models;

class OrderUpdate
{
    public function __construct()
    {
        $this->config = new \Mobbex\PS\Checkout\Models\Config();
        $this->logger = new \Mobbex\PS\Checkout\Models\Logger();
    }
    
    /**
     * Update the order payment information.
     * 
     * @param \Order $order
     * @param array $data Transaction data.
     * 
     * @return bool Result of update.
     */
    public function updateOrderPayment($order, $data)
    {
        try {
            $payments = $order->getOrderPaymentCollection() ?: [];
            $payment  = isset($payments[0]) ? $payments[0] : new \OrderPayment;

            if (!$payment || \Mobbex\PS\Checkout\Models\Helper::getState($data['status']) != 'approved')
                return false;

            $payment->order_reference = $order->reference;
            $payment->id_currency     = $order->id_currency;
            $payment->conversion_rate = 1;
            $payment->amount          = $data['total'];
            $payment->payment_method  = $data['source_name'];
            $payment->transaction_id  = $data['payment_id'] ?: null;
            $payment->card_number     = $data['source_number'] ?: null;
            $payment->card_expiration = $data['source_expiration'] ? implode('/', json_decode($data['source_expiration'], true)) : null;
            $payment->card_holder     = $data['cardholder'] ? json_decode($data['cardholder'], true)['name'] : null;
            $payment->card_brand      = $data['source_type'];

            // If is new payment, update order real paid
            if (!isset($payments[0]))
                $order->total_paid_real = $order->total_paid;

            return $payment->save() && $order->update();
        } catch (\Exception $e) {
            $this->logger->log('error', 'OrderUpdate > updateOrderPayment | Error Updating Order Payment on Webhook Process', [ 'data ' => $e->getMessage(), 'order id' => $order->id]);
        }
    }

    /**
     * Remove expiration tasks from an order.
     * 
     * @param \Order $order
     * 
     * @return bool Result of execution.
     */
    public function removeExpirationTasks($order)
    {
        if (\Mobbex\PS\Checkout\Models\Helper::needUpgrade())
            return false;

        $tasks = $this->getExpirationTasks($order);

        foreach ($tasks as $task) {
            if (!$task->delete()) {
                $this->logger->log('error', 'OrderUpdate > removeExpirationTask | Error removing order expiration task on Webhook', ['order_id' => $order->id]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get all expiration tasks from an order.
     * 
     * @param \Order $order
     * 
     * @return array
     */
    public function getExpirationTasks($order)
    {
        $tasks = new \PrestaShopCollection('\Mobbex\PS\Checkout\Models\Task');
        $tasks->where('name', '=', 'actionMobbexExpireOrder');
        $tasks->where('args', '=', "[$order->id]");

        return $tasks->getResults() ?: [];
    }
}