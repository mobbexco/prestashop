<div class="form-group row">
    <label class="form-control-label control-label col-lg-3">
        <span class="label-tooltip" data-original-title="{l s='Active los planes que desee que aparezcan en el checkout' mod='mobbex'}">
            {l s='Cofiguración de planes' mod='mobbex'}
        </span>
        <span class="help-box" data-toggle="popover" data-content="{l s='Active los planes que desee que aparezcan en el checkout' mod='mobbex'}">
        </span>
    </label>
    <div class="{if constant('_PS_VERSION_') < '1.7'}col-lg-9{/if} col-sm">
        {include file="./plans-filter.tpl"}
    </div>
    <div class="col-md-12">
        {include file="./multivendor.tpl"}
    </div>
</div>