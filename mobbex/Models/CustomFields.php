<?php

namespace Mobbex\PS\Checkout\Models;

class CustomFields extends \ObjectModel
{
    public $row_id;
    public $object;
    public $field_name;
    public $data;

    public static $definition = array(
        'table' => 'mobbex_custom_fields',
        'primary' => 'id',
        'multilang' => false,
        'fields' => array(
            'id' => array('type' => self::TYPE_INT, 'required' => false),
            'row_id' => array('type' => self::TYPE_INT, 'required' => false),
            'object' => array('type' => self::TYPE_STRING, 'required' => false),
            'field_name' => array('type' => self::TYPE_STRING, 'required' => false),
            'data' => array('type' => self::TYPE_STRING, 'required' => false),
        ),
    );

    /**
     * Saves custom field data
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * 
     * @return boolean
     */
    public static function saveCustomField($row_id, $object, $field_name, $data)
    {
        // If the field for that object already exists
        $previousValue = \Mobbex\PS\Checkout\Models\CustomFields::getCustomField($row_id, $object, $field_name, 'id');

        // Save custom field
        $customField = new \Mobbex\PS\Checkout\Models\CustomFields($previousValue?:null);

        $customField->row_id = $row_id;
        $customField->object = $object;
        $customField->field_name = $field_name;
        $customField->data = $data;

        $customField->save();
    }

    /**
     * Get custom field data
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * @param string $searched_column
     * 
     * @return string
     */
    public static function getCustomField($row_id, $object, $field_name, $searched_column = 'data')
    {
        $custom_field = new \Mobbex\PS\Checkout\Models\CustomFields();

        $sql = new \DbQuery();
        $sql->select($searched_column);
        $sql->from('mobbex_custom_fields', 'f');
        $sql->where('f.row_id = ' . $row_id);
        $sql->where("f.object = '$object'");
        $sql->where("f.field_name = '$field_name'");
        $sql->limit(1);

        $result = \Db::getInstance()->executeS($sql);
        return !empty($result[0][$searched_column]) ? $result[0][$searched_column] : false;
    }
}