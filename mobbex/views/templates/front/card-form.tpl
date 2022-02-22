<form id="card_{$key}_form" class="walletForm additional-information form-group ps17" method="post" card="{$key}">
    <input type="password" name="securityCode" id="card-{$key}-code" class="form-control" placeholder="{$card['source']['card']['product']['code']['name']}" maxlength="{$card['source']['card']['product']['code']['length']}">
    <select name="installment" id="card-{$key}-installments" class="form-control form-control-select">
        {foreach from=$card['installments'] item=installment}
            <option value="{$installment['reference']}">{$installment['name']}</option>
        {/foreach}
    </select>
    <input type="hidden" name="walletCard" value="{$key}">
    <input type="hidden" name="cardNumber" id="card-{$key}-number" value="{$card['card']['card_number']}">
</form>