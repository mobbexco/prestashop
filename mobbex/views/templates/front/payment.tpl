{if constant('_PS_VERSION_') < '1.7'}
    {if $methods}
        {foreach from=$methods item=method}
            <div class="row">
                <div class="col-xs-12">
                    <p class="payment_module">
                        <a class="mbbx-method payment-option" href="#" group="{$method['group']}:{$method['subgroup']}">
                            <img src="{$method['subgroup_logo']}" style="">
                            {if (count($methods) == 1 || $method['subgroup'] == 'card_input') && Configuration::get('MOBBEX_TITLE')}
                                {Configuration::get('MOBBEX_TITLE')}
                            {else}
                                {$method['subgroup_title']}
                            {/if}
                        </a>
                    </p>
                </div>
            </div>
        {/foreach}
    {else}
        <div class="row">
            <div class="col-xs-12">
                <p class="payment_module">
                    <a class="mbbx-method" href="#" style="background: url({$base_dir}modules/mobbex/views/img/logo_transparent.png) 15px 15px no-repeat;">
                        {if Configuration::get('MOBBEX_TITLE')}
                            {Configuration::get('MOBBEX_TITLE')}
                        {elseif $cards}
                            {l s='Use other Card/Payment Method' mod='mobbex'}
                        {else}
                            {l s='Pay with Credit/Debit Cards' mod='mobbex'}
                        {/if}
                    </a>
                </p>
            </div>
        </div>
    {/if}
    {foreach from=$cards item=card key=key}
        <div class="row">
            <div class="col-xs-12 mbbx-card">
                <p class="payment_module">
                    <a class="payment-option walletAnchor" href="#" card="{$key}">
                        <img src="{$card['source']['card']['product']['logo']}" style="">
                        {$card['name']}
                    </a>
                </p>
                <div class="walletForm" card="{$key}">
                    <input type="password" name="securityCode" id="card-{$key}-code" placeholder="{$card['source']['card']['product']['code']['name']}" maxlength="{$card['source']['card']['product']['code']['length']}">
                    <select name="installment" id="card-{$key}-installments" >
                        {foreach from=$card['installments'] item=installment}
                            <option value="{$installment['reference']}">{$installment['name']}</option>
                        {/foreach}
                    </select>
                    <input type="hidden" name="cardNumber" id="card-{$key}-number" value="{$card['card']['card_number']}">
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
    <form id="mobbex_checkout" class="mbbx-method" action="{$checkoutUrl}" method="post"></form>
{/if}