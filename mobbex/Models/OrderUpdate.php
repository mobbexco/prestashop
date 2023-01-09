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
     * @return bool|null Result of update. Null if not applicable.
     */
    public function updateOrderPayment($order, $data)
    {
        try {
            $payments = $order->getOrderPaymentCollection() ?: [];
            $payment  = isset($payments[0]) ? $payments[0] : new \OrderPayment;

            if (!$payment || \Mobbex\PS\Checkout\Models\Helper::getState($data['status']) != 'approved')
                return;

            // First, decode jsons to use data safely
            $sourceExpiration = json_decode($data['source_expiration'], true); // chemes
            $cardHolder       = json_decode($data['cardholder'], true);

            $payment->order_reference = $order->reference;
            $payment->id_currency     = $order->id_currency;
            $payment->conversion_rate = 1;
            $payment->amount          = $data['total'];
            $payment->payment_method  = $data['source_name'];
            $payment->transaction_id  = $data['payment_id'] ?: null;
            $payment->card_number     = $data['source_number'] ?: null;
            $payment->card_expiration = $sourceExpiration ? implode('/', $sourceExpiration) : null;
            $payment->card_holder     = isset($cardHolder['name']) ? $cardHolder['name'] : null;
            $payment->card_brand      = $data['source_type'];

            // If is new payment, update order real paid
            if (!isset($payments[0]))
                $order->total_paid_real = $order->total_paid;

            return $payment->save() && $order->update();
        } catch (\Exception $e) {
            $this->logger->log('error', 'OrderUpdate > updateOrderPayment | Error Updating Order Payment on Webhook Process', ['msg' => $e->getMessage(), 'order_id' => $order->id]);
        }

        return false;
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

        try {
            if ($diff > 0)
                return $this->addCartDiscount($cart, $diff);
            else if ($diff < 0)
                $this->addCartCost($cart, abs($diff)) && \Cart::resetStaticCache();

            $cart->save();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error updating cart total on Webhook', [$cart->id, $amount, $e->getMessage()]);
        }
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
            'id_customer'        => $cart->id_customer,
            'date_from'          => date('Y-m-d 00:00:00'),
            'date_to'            => date('Y-m-d 23:59:59'),
            'name'               => array_fill_keys(\Language::getIds(), 'Descuento financiero'),
            'quantity'           => 1,
            'quantity_per_user'  => 1,
            'priority'           => 1,
            'partial_use'        => 1,
            'reduction_amount'   => $amount,
            'reduction_tax'      => 1,
            'reduction_currency' => 1,
        ]);

        // Save cart rule to db and return
        $cartRule->add() && $cart->addCartRule($cartRule->id);
        //Return the cart rule to delete it
        return $cartRule; 
    }

    /**
     * Try to add a cost to cart using custom product.
     * 
     * @param \Cart $cart
     * @param float|string $amount
     * 
     * @return bool|null Save result. Null if product is not configured.
     */
    public function addCartCost($cart, $amount)
    {
        $productId = \Mobbex\PS\Checkout\Models\Helper::getProductIdByReference('mobbex-cost');

        // Exit if product not exists or it was already added
        if (!$productId)
            return;

        $specificPrice = new \SpecificPrice;
        $specificPrice->hydrate([
            'id_product'           => $productId,
            'id_customer'          => $cart->id_customer,
            'id_cart'              => $cart->id,
            'id_shop'              => $cart->id_shop,
            'id_currency'          => $cart->id_currency,
            'id_country'           => \Context::getContext()->country->id,
            'id_group'             => 0,
            'from'                 => date('Y-m-d 00:00:00'),
            'to'                   => date('Y-m-d 23:59:59'),
            'from_quantity'        => 1,
            'price'                => $amount,
            'reduction_type'       => 'amount',
            'reduction_tax'        => 1,
            'reduction'            => 0,
        ]);

        // Save specific price of product to db and return
        return $specificPrice->add() && (
            !empty($cart->getProductQuantity($productId)['quantity']) ?: $cart->updateQty(1, $productId)
        ); 
    }
}