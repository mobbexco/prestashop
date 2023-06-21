<?php

namespace Mobbex\PS\Checkout\Models;

class CartRules
{
    /**
     * Get the cart rules and apply the corresponding discounts
     * 
     * @param array $rules
     * @param array $products
     * 
     * @return array $rulesProducts
     * 
     */
    public static function getRules($rules, $products)
    {
        // Traverse each rule considering parallel rules
        $rulesProducts = [];
        foreach ($rules as $rule)
            if ($rule['reduction_product'] == '0')
                $rulesProducts = self::getOrderRule($rule, !empty($rulesProducts) ? $rulesProducts : $products);
            elseif ($rule['reduction_product'] == '-1')
                $rulesProducts = self::getChepeastRule($rule, !empty($rulesProducts) ? $rulesProducts : $products);
            else
                $rulesProducts = self::getSpecificRule($rule, !empty($rulesProducts) ? $rulesProducts : $products);

        return self::applyDiscount($rulesProducts);
    }

    /**
     * Apply order discount to every product
     * 
     * @param array $rule
     * @param array $products
     * 
     * @return array $rulesProducts
     * 
     */    
    public static function getOrderRule($rule, $products)
    {
        foreach($products as $product){
            // Create a new position to store the discount values per product
            $product['rules_discount'] = self::storeDiscount($product, $rule);
            $rulesProducts[] = $product;
        }
        return $rulesProducts;
    }
 
    /**
     * Search for the product with the lowest price and apply the discount
     * 
     * @param array $rule
     * @param array $products
     * 
     * @return array
     * 
     */
    public static function getChepeastRule($rule, $products)
    {
        $chepeast = min(array_column($products, 'price_wt'));
        return self::getRuleProduct($products, $rule, ['price_wt', $chepeast]);
    }

    /**
     * Search for an specific product with rule and apply the discount
     * 
     * @param array $rule
     * @param array $products
     * 
     * @return array $rulesProducts
     * 
     */
    public static function getSpecificRule($rule, $products)
    {
        return self::getRuleProduct($products, $rule, ['id_product', $rule['reduction_product']]);
    }

    /**
     * Search for product and create a new position with cart rule discount
     * 
     * @param array $rule
     * @param array $products
     * @param array $conditions
     * 
     * @return array $rulesProducts
     * 
     */
    public static function getRuleProduct($products, $rule, $conditions)
    {
        foreach ($products as $product){
            if ($product[$conditions[0]] == $conditions[1])
                // Stores the discount values per product in a new position
                $product['rules_discount'] = self::storeDiscount($product, $rule);
            $rulesProducts[] = $product;
        }
        return $rulesProducts;
    }

    /**
     * Stores the discount values per product considering previously saved values 
     * 
     * @param array $product
     * @param array $rule
     * 
     * @return $discount
     * 
     */
    public static function storeDiscount($product, $rule)
    {
        if (empty($product['rules_discount']))
            return self::getDiscount($rule, $product['price_wt']);
        else
            return $product['rules_discount'] + self::getDiscount($rule, $product['price_wt']);
    }

    /**
     * Gets the discount of the product with the cart rule applied
     * 
     * @param array $rule
     * @param int   $product_price
     * 
     * @return int $discount
     * 
     */
    public static function getDiscount($rule, $product_price)
    {
        // Get cart rule discount
         return $rule['reduction_percent'] ? round($product_price * $rule['reduction_percent']/100, 2) : round($rule['reduction_amount'], 2);
    }

    /**
     * Apply discount(s) from cart rule(s) to each product that has one 
     * 
     * @param array $rulesProducts
     * 
     * @return array $discountProducts
     * 
     */
    public static function applyDiscount($rulesProducts)
    {
        foreach ($rulesProducts as $rulesProduct){
            // If product has discount applies it
            if (isset($rulesProduct['rules_discount']))
                $rulesProduct['price_wt'] = $rulesProduct['price_wt'] - $rulesProduct['rules_discount'];
            $discountProducts[] = $rulesProduct;
        }
        return $discountProducts;
    }
}