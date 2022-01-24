<?php

namespace Mobbex;

class OrderUpdate
{
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
            $payment  = isset($payments[0]) ? $payments[0] : false;

            if (!$payment)
                return false;

            $payment->payment_method  = $data['source_name'];
            $payment->transaction_id  = $data['payment_id'] ?: null;
            $payment->card_number     = $data['source_number'] ?: null;
            $payment->card_expiration = $data['source_expiration'] ? implode('/', json_decode($data['source_expiration'], true)) : null;
            $payment->card_holder     = $data['cardholder'] ? json_decode($data['cardholder'], true)['name'] : null;
            $payment->card_brand      = $data['source_type'];

            return $payment->update();
        } catch (\Exception $e) {
            \MobbexHelper::log('Error Updating Order Payment on Webhook Process: ' . $e->getMessage(), $order->id, true);
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
        if (\MobbexHelper::needUpgrade())
            return false;

        $tasks = $this->getExpirationTasks($order);

        foreach ($tasks as $task) {
            if (!$task->delete()) {
                \MobbexHelper::log('Error removing order expiration task on Webhook', $order->id, true);
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
        $tasks = new \PrestaShopCollection('MobbexTask');
        $tasks->where('name', '=', 'actionMobbexExpireOrder');
        $tasks->where('args', '=', "[$order->id]");

        return $tasks->getResults() ?: [];
    }
}