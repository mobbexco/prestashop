<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_4_0($module)
{
    // Get all plans saved using previous method
    $sql = new DbQuery();
    $sql->select('*');
    $sql->from('mobbex_custom_fields');
    $sql->where("field_name = 'ahora_3' || field_name = 'ahora_6' || field_name = 'ahora_12' || field_name = 'ahora_18'");

    foreach (Db::getInstance()->executeS($sql) as $field) {
        if ($field['data'] == 'yes') {
            // Save using new method
            $commonPlans = json_decode(\Mobbex\PS\Checkout\Models\CustomFields::getCustomField($field['row_id'], $field['object'], 'common_plans')) ?: [];

            if (!in_array($field['field_name'], $commonPlans)) {
                $commonPlans[] = $field['field_name'];
                \Mobbex\PS\Checkout\Models\CustomFields::saveCustomField($field['row_id'], $field['object'], 'common_plans', json_encode($commonPlans));
            }
        }
    }

    // Remove deprecated saved data
    return Db::getInstance()->delete(
        'mobbex_custom_fields', 
        "field_name = 'ahora_3' || field_name = 'ahora_6' || field_name = 'ahora_12' || field_name = 'ahora_18'"
    );
}