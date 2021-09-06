<table class="mbbx-plans-cont">
    <tbody>
        <tr style="text-align: center;">
            <td>{l s='Planes comunes' mod='mobbex'}</td>
            <td>{l s='Planes con reglas avanzadas' mod='mobbex'}</td>
        </tr>
        <tr>
            <td class="mbbx-plans">
                {foreach from=$commonFields key=key item=field}
                    <div class="mbbx-plan">
                        <input type="hidden" name="{$field['id']}" value="no">
                        <input type="checkbox" name="{$field['id']}" value="yes" {if $field['value']}checked="checked"{/if} id="{$field['id']}">
                        <label for="{$field['id']}">{$field['label']}</label>
                    </div>
                {/foreach}
            </td>
            <td class="mbbx-plans">
                {foreach from=$advancedFields key=sourceRef item=fields}
                    <div class="mbbx-plan-source">
                        <img src="https://res.mobbex.com/images/sources/{$sourceRef}.png">
                        <p>{$sourceNames[$sourceRef]}</p>
                    </div>
                    {foreach from=$fields key=key item=field}
                        <div class="mbbx-plan-advanced">
                            <input type="checkbox" id="{$field['id']}" name="{$field['id']}" {if $field['value']}checked="checked" value="yes"{/if}>
                            <label for="{$field['id']}">{$field['label']}</label>
                        </div>
                    {/foreach}
                {/foreach}
            </td>
        </tr>
    </tbody>
</table>

<style>
    .mbbx-plans-cont {
        border: 1px gainsboro solid;
        width: 500px;
    }

    .mbbx-plans-cont tbody {
        vertical-align: top
    }

    .mbbx-plans-cont td {
        width: 50%;
        border: 1px gainsboro solid;
        padding: 15px;
    }

    .mbbx-plans-cont label {
        font-weight: 400 !important;
    }

    .mbbx-plan-advanced {
        padding-left: 20px;
    }

    .mbbx-plan-source * {
        display: inline;
    }

    .mbbx-plan-source img {
        width: 30px;
        border-radius: 100%;
    }
</style>