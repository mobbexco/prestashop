<?php

namespace Mobbex\PS\Checkout\Models;

class Cache extends AbstractModel
{
    public $cache_key;
    public $data;
    public $date;

    public static $definition = array(
        'table' => 'mobbex_cache',
        'primary' => 'cache_key',
        'multilang' => false,
        'fields' => array(
            'cache_key' => array('type' => self::TYPE_STRING, 'required' => true),
            'data'      => array('type' => self::TYPE_STRING, 'required' => false),
        ),
    );

    /**
     * Store data in mobbex cache table.
     * 
     * @param string $key Identifier key for data to store.
     * @param string $data Data to store.
     * @return boolean
     */
    public function store($key, $data)
    {
        $this->cache_key = $key;
        $this->data      = $data;

        $this->save();
    }

    /**
     * Get data stored in mobbex chache table.
     * 
     * @param string $key Identifier key for cache data.
     * @return string|bool $data Data to store.
     */
    public function get($key)
    {
        //Delete expired values
        self::deleteExpiredCache();
        
        //Get data
        $result = \Db::getInstance()->executes("SELECT * FROM " . _DB_PREFIX_ . "mobbex_cache WHERE `cache_key` = '$key';");

        return !empty($result[0]['data']) ? json_decode($result[0]['data'], true) : false;
    }

    /**
     * Delete expired stored data in cache table.
     */
    public static function deleteExpiredCache()
    {
        return \Db::getInstance()->execute("DELETE FROM " . _DB_PREFIX_ . "mobbex_cache WHERE `date` < DATE_SUB(NOW(), INTERVAL 5 MINUTE);");
    }
}
