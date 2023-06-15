<?php

namespace Mobbex\PS\Checkout\Models;

class Installer
{
    public $sdk_sql = ['cache', 'custom_fields'];

    /**
     * Create module tables if these do not exist.
     * 
     * @return bool Creation result. 
     */
    public function createTables()
    {
        $db = \DB::getInstance();

        foreach (['cache', 'custom_fields', 'task', 'transaction'] as  $table) {

            $db->execute("SHOW TABLES LIKE '" . _DB_PREFIX_ . "mobbex_$table';");
            $tableExist = $db->numRows();

            if ($tableExist && $table === 'transaction') {
                if(!$this->checkTransactionTable($db))
                    return false;
            } else if (!$tableExist) {
                if(!$this->installTable($table, $db))
                    return false;
            }
        }
        return true;
    }

    /**
     * Check the schema of mobbex transaction table & alter it if its necesary.
     * 
     * @param object $db Prestashop db connection.
     * 
     * @return bool
     * 
     */
    public function checkTransactionTable($db)
    {
        // Add column childs if not exists
        if (!$db->execute("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "mobbex_transaction` WHERE FIELD = 'childs';") && !$db->execute("ALTER TABLE " . _DB_PREFIX_ . "mobbex_transaction ADD COLUMN childs TEXT NOT NULL;"))
            return false;

        // Check if table has already been modified
        if ($db->execute("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "mobbex_transaction` WHERE FIELD = 'id' AND EXTRA LIKE '%auto_increment%';"))
         return true;

        // If it was modified but id has not auto_increment property, add to column
        if ($db->execute("SHOW COLUMNS FROM `" . _DB_PREFIX_ . "mobbex_transaction` WHERE FIELD = 'id';") && !$db->execute("ALTER TABLE `" . _DB_PREFIX_ . "mobbex_transaction` MODIFY `id` INT NOT NULL AUTO_INCREMENT;"))
            return false;

        //Alter the table
        return $db->execute(str_replace(['DB_PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], file_get_contents(dirname(__FILE__) . '/../sql/alter.sql')));
    }

    /**
     * Install a table from sql scripts.
     * 
     * @param string $table Table name without db & mobbex prefix .
     * @param object $db connection.
     * 
     */
    public function installTable($table, $db)
    {
        //Get query
        $query = str_replace(
            ['DB_PREFIX_', 'ENGINE_TYPE'],
            [_DB_PREFIX_, _MYSQL_ENGINE_],
            file_get_contents(dirname(__FILE__) . "/../" . (in_array($table, $this->sdk_sql) ? "vendor/mobbexco/php-plugins-sdk/src/" : '') . "sql/$table.sql")
        );

        //Execute query
        return $db->execute($query);
    }

    /**
     * Create a list of states if these do not exist.
    * 
     * @param array $statesData
     * 
     * @return bool Save result.
     */
    public function createStates($statesData)
    {
        $result = true;

        foreach ($statesData as $stateData)
            $result &= $this->stateExists($stateData['name']) || $this->createState($stateData);

        return $result;
    }

    /**
     * Create finnacial cost product if it do not exist.
     * 
     * @return bool Creation result. 
     */
    public function createCostProduct()
    {
        // Try to create finnacial cost product
        $productId = \Mobbex\PS\Checkout\Models\OrderHelper::getProductIdByReference('mobbex-cost');
        $product   = $productId ? new \Product($productId) : $this->createHiddenProduct('mobbex-cost', 'Costo financiero');

        // Always update product quantity
        if ($product->id)
            \StockAvailable::setQuantity($product->id, null, 9999999);

        return (bool) $product->id;
    }

    /**
     * Check if the custom state already exists on db.
     * 
     * @param string $name The name of the state.
     * 
     * @return bool
     */
    public function stateExists($name)
    {
        return \Configuration::hasKey($name)
            && \Configuration::get($name)
            && \Validate::isLoadedObject(new \OrderState(\Configuration::get($name)));
    }

    /**
     * Create a custom state on db.
     * 
     * @param array $stateData An associative array with name, label, color and send_email.
     * 
     * @return bool Save result.
     */
    public function createState($stateData)
    {
        $state = new \OrderState;
        $state->hydrate([
            'name'        => $stateData['label'],
            'color'       => $stateData['color'],
            'send_email'  => $stateData['send_email'],
            'module_name' => 'mobbex',
            'hidden'      => false,
            'delivery'    => false,
            'logable'     => false,
            'invoice'     => false,
        ], \Configuration::get('PS_LANG_DEFAULT'));

        // Add to database
        return $state->save() && \Configuration::updateValue($stateData['name'], (int) $state->id);
    }

    /**
     * Create a hidden product.
     * 
     * @param string $reference String to identify and get product after.
     * @param string $name The name of product.
     * 
     * @return \Product
     */
    public function createHiddenProduct($reference, $name)
    {
        $product = new \Product;
        $product->hydrate([
            'reference'           => $reference,
            'name'                => $name,
            'quantity'            => 9999999,
            'is_virtual'          => false,
            'indexed'             => 0,
            'visibility'          => 'none',
            'id_category_default' => \Configuration::get('PS_HOME_CATEGORY'),
            'link_rewrite'        => $reference,
        ], \Configuration::get('PS_LANG_DEFAULT'));

        // Save to db
        $product->save();
        $product->addToCategories(\Configuration::get('PS_HOME_CATEGORY'));

        return $product;
    }
}