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
            $payment  = isset($payments[0]) ? $payments[0] : new \OrderPayment;

            if (!$payment || \MobbexHelper::getState($data['status']) != 'approved')
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

    /**
     * Update cart totals adding discounts or fees maked on checkout.
     * 
     * @param \Cart|int $cart Cart instance or him id.
     * @param float|string $amount The amount paid on Mobbex.
     */
    public function updateCartTotal($cart, $amount)
    {
        // Instance cart if needed
        if (is_numeric($cart))
            $cart = new \Cart($cart);

        // Calculate amount diff
        $diff = (float) $cart->getOrderTotal() - $amount;

        if ($diff > 0)
            $this->addCartDiscount($cart, $diff);

        $cart->save();
    }

    /**
     * Add a discount to cart using cart rules.
     * 
     * @param \Cart $cart
     * @param float|string $amount
     * 
     * @return bool Save result.
     */
    public function addCartDiscount($cart, $amount)
    {
        $cartRule = new \CartRule;
        $cartRule->hydrate([
            'name'               => array_fill_keys(\Language::getIds(), 'Descuento financiero'),
            'id_customer'        => $cart->id_customer,
            'date_from'          => date('Y-m-d 00:00:00'),
            'date_to'            => date('Y-m-d 23:59:59'),
            'quantity'           => 1,
            'quantity_per_user'  => 1,
            'priority'           => 1,
            'partial_use'        => 1,
            'reduction_amount'   => $amount,
            'reduction_tax'      => 1,
            'reduction_currency' => 1,
        ]);

        // Save cart rule to db and return
        return $cartRule->add() && $cart->addCartRule($cartRule->id);
    }
}