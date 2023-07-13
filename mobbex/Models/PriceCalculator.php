<?php

namespace Mobbex\PS\Checkout\Models;

class PriceCalculator
{
    /** Products from cart */
    public $products;
    /** Rules from cart */
    public $cartRules;

    public function __construct($cart){
        $this->products  = $cart->getProducts(true);
        $this->cartRules = $cart->getCartRules();
    }
    
    /**
     * Apply the corresponding cart rules discounts to each product in the order
     * 
     * @return array $products products with discount applied
     * 
     */
    public function applyCartRules()
    {
        // Get cart products
        $products = $this->products;
        // Traverse each Cart Rule
        foreach ($this->cartRules as $rule){
            // Order Cart Rule
            if ($rule['reduction_product'] == '0')
                $products = $this->getRuleProduct($products, $rule);
            // Chepeast Product Cart Rule
            elseif ($rule['reduction_product'] == '-1')
                $products = $this->getRuleProduct($products, $rule, ['price_wt', min(array_column($products, 'price_wt'))]);
            // Specific Product Cart Rule
            else
                $products = $this->getRuleProduct($products, $rule, ['id_product', $rule['reduction_product']]);
        }
        // Apply discount to every product with Cart Rule
        return array_map([$this, 'applyProductDiscount'], $products);
    }

    /**
     * Search for product and create a new position with cart rule discount
     * 
     * @param array $rule       actual cart rule
     * @param array $products   products from the cart
     * @param array $conditions conditions to evaluate if the product corresponds to a cart rule
     * 
     * @return array $products  products with rules discounts position
     * 
     */
    public function getRuleProduct($products, $rule, $conditions = [])
    {
        // Get the rule discount values per product in a new position
        foreach ($products as &$product){
            if (empty($conditions))
                $product['rules_discount'] = $this->getDiscount($product, $rule);
            elseif ($product[$conditions[0]] == $conditions[1])
                $product['rules_discount'] = $this->getDiscount($product, $rule);
        }
        return $products;
    }

    /**
     * Gets the discount of the product with the cart rule applied
     * 
     * @param array $rule
     * @param int   $product
     * 
     * @return int  product acumulated discount
     * 
     */
    public function getDiscount($product, $rule)
    {
        // Get cart rule discount. Add it to the accumulated rules discount in product if it is the case
        if (empty($product['rules_discount']))
            return $this->calculateDiscount($rule, $product['price_wt']);
        else
            return $product['rules_discount'] + $this->calculateDiscount($product['price_wt'] - $product['rules_discount'], $rule);
    }

    /**
     * Calculate the price of a product with discount
     * 
     * @param array $rule
     * @param int   $price product price without taxes
     * 
     * @return int  discount
     * 
     */
    public function calculateDiscount($price, $rule)
    {
        // Checks if the type of discount is a percentage or a fixed amount and applies it
        return $rule['reduction_percent'] != '0' ? $price * $rule['reduction_percent']/100 : $rule['reduction_amount'];
    }

    /**
     * Applies to the product price the total discount from cart rule/s
     * 
     * @param array  $product
     * 
     * @return array $product product with new price without taxes
     * 
     */
    public function applyProductDiscount($product)
    {
        if (isset($product['rules_discount']))
           $product['price_wt'] = $product['price_wt'] - $product['rules_discount'];
        
        return $product;
    }
}