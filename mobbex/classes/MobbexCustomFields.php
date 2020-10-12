<?php
/**
 * MobbexCustomFields.php
 *
 * Custom fields model
 *
 * @author  Mobbex Co <admin@mobbex.com>
 * @version 1.4.6
 * @see     PaymentModuleCore
 */
class MobbexCustomFields extends ObjectModel
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
     * Saves the custom field with the data
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
        // if the field for that object already exists
        $previous_value = MobbexCustomFields::getCustomField($row_id, $object, $field_name);

        // Save custom field
        $custom_field = new MobbexCustomFields($previous_value['id']?:null);

        $custom_field->row_id = $row_id;
        $custom_field->object = $object;
        $custom_field->field_name = $field_name;
        $custom_field->data = $data;

        $custom_field->save();
    }

    public static function getCustomField($row_id, $object, $field_name)
    {
        $custom_field = new MobbexCustomFields();

        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('mobbex_custom_fields', 'f');
        $sql->where('f.row_id = ' . $row_id);
        $sql->where("f.object = '$object'");
        $sql->where("f.field_name = '$field_name'");
        $sql->limit(1);

        $result = Db::getInstance()->executeS($sql);
        return !empty($result[0]) ? $result[0] : false;
    }
}