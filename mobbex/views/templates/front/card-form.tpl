<form id="card_{$key}_form" class="walletForm additional-information form-group ps17" method="post" card="{$key}">
    <input type="password" name="securityCode" class="form-control" placeholder="{$card['source']['card']['product']['code']['name']}" maxlength="{$card['source']['card']['product']['code']['length']}">
    <select name="installment" class="form-control form-control-select">
        {foreach from=$card['installments'] item=installment}
            <option value="{$installment['reference']}">{$installment['name']}</option>
        {/foreach}
    </select>
    <input type="hidden" name="intentToken" value="{$card['it']}">
    <input type="hidden" name="walletCard" value="{$key}">
</form>