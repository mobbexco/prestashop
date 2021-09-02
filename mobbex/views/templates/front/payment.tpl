{if constant('_PS_VERSION_') < '1.7'}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module">
                <a id="mbbx-anchor" href="#" style="background-image: url({$base_dir}modules/mobbex/logo_transparent.png);">
                    {if $cards}
                        {l s='Use other Card/Payment Method' mod='mobbex'}
                    {else}
                        {l s='Pay with Credit/Debit Cards' mod='mobbex'}
                    {/if}
                </a>
            </p>
        </div>
    </div>
    {foreach from=$cards item=card key=key}
        <div class="row">
            <div class="col-xs-12 mbbx-card">
                <p class="payment_module">
                    <a class="payment-option" href="#" onclick="return activeCard({$key})">
                        <img src="{$card['source']['card']['product']['logo']}" style="">
                        {$card['name']}
                    </a>
                </p>
                <div id="card_{$key}_form" class="walletForm">
                    <input type="password" name="securityCode" placeholder="{$card['source']['card']['product']['code']['name']}" maxlength="{$card['source']['card']['product']['code']['length']}">
                    <select name="installment">
                        {foreach from=$card['installments'] item=installment}
                            <option value="{$installment['reference']}">{$installment['name']}</option>
                        {/foreach}
                    </select>
                    <input type="hidden" name="intentToken" value="{$card['it']}">
                    <input type="hidden" name="walletCard" value="{$key}">
                    <button type="submit" id="mobbexExecute" class="button btn btn-default button-medium pull-right">
                        <span>
                            {l s='I confirm my order' mod='mobbex'}
                            <i class="icon-chevron-right right"></i>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    {/foreach}
{else}
    <form id="mobbex_checkout" method="post"></form>
    <div id="mobbexWallet" class="additional-information"></div>
{/if}
<div id="mbbx-container"></div>