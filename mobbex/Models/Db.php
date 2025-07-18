<?php

namespace Mobbex\PS\Checkout\Models;

/**
 * Db Class
 * 
 * This class alow the Mobbex php-plugins-sdk interact with platform database.
 */
class Db extends \Mobbex\Model\Db
{
    /**
     * Executes a sql query & return the results.
     * 
     * @param string $sql
     * 
     * @return bool|array
     */
    public function query($sql)
    {
        $result = \Db::getInstance()->query($sql);

        // If the query fails, throw an exception with the error message
        $error = \Db::getInstance()->getMsgError();
        $errorNumber = \Db::getInstance()->getNumberError();

        if ($error)
            throw new \Exception("Error executing query: $error ($errorNumber). SQL: $sql");

        // If isn't a select type query return bool
        if (!preg_match('#^\s*\(?\s*(select|show|explain|describe|desc)\s#i', $sql))
            return (bool) $result;

        // Return the results in array assoc format
        if ($result instanceof \PDOStatement)
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        else if ($result instanceof \mysqli_result)
            return $result->fetch_all(MYSQLI_ASSOC);
    }
}
