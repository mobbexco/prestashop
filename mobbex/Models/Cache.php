<?php

namespace Mobbex\PS\Checkout\Models;

class Cache
{
    /**
     * Retrieve data stored for a cache key.
     * 
     * @param string $key Unique identifier key.
     * 
     * @return mixed Data decoded.
     */
    public function get($key)
    {
        $this->deleteExpiredCache();

        $result = \Db::getInstance()->executes(
            "SELECT * FROM " . _DB_PREFIX_ . "mobbex_cache WHERE `cache_key` = '$key';"
        );

        return !empty($result[0]['data']) ? json_decode($result[0]['data'], true) : null;
    }

    /**
     * Store cache data to db.
     * 
     * @param string $key Unique identifier key.
     * @param mixed $data Data to store.
     * 
     * @return bool Save result.
     */
    public function store($key, $data)
    {
        return \Db::getInstance()->execute(
            "REPLACE INTO " . _DB_PREFIX_ . "mobbex_cache (`cache_key`, `data`) VALUES ('{$key}', '{$data}');"
        );
    }

    /**
     * Delete expired stored data in cache table.
     * 
     * @return bool Deletion result.
     */
    public function deleteExpiredCache()
    {
        return \Db::getInstance()->execute(
            "DELETE FROM " . _DB_PREFIX_ . "mobbex_cache WHERE `date` < DATE_SUB(NOW(), INTERVAL 5 MINUTE);"
        );
    }
}