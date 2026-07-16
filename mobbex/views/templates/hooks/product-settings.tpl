<div class="translations tabbable">
    <div class="translationsFields tab-content">
        <div class="form-group">
            <h2>{l s='Plans Configuration' mod='mobbex'}</h2>
            <p class="subtitle">{l s='Check the plans that will be shown at checkout' mod='mobbex'}</p>
        </div>
        <div class="form-group">
            {include file="./plans-filter.tpl"}
        </div>
        <div class="form-group">
            <h2>{l s='Multivendor' mod='mobbex'}</h2>
            <p class="subtitle">{l s='Put the UID of the entity selected for this product.' mod='mobbex'}</p>
        </div>
        <div class="form-group">
            {include file="./multivendor.tpl"}
        </div>
        <div class="form-group">
            <h2>{l s='Subscriptions' mod='mobbex'}</h2>
            <p class="subtitle">{l s='Check if the product is a subscription.' mod='mobbex'}</p>
        </div>
        <div class="form-group">
            {include file="./subscription-option.tpl"}
        </div>
        {hook h="displayMobbexProductSettings" id="$id"}
    </div>
</div>