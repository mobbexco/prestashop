<div class="form-group row">
    <label class="form-control-label control-label col-lg-3">
        <span class="label-tooltip" data-original-title="{l s='Check the plans that will be shown at checkout' mod='mobbex'}">
            {l s='Plans Configuration' mod='mobbex'}
        </span>
        <span class="help-box" data-toggle="popover" data-content="{l s='Check the plans that will be shown at checkout' mod='mobbex'}">
        </span>
    </label>
    <div class="{if constant('_PS_VERSION_') < '1.7'}col-lg-9{/if} col-sm">
        {include file="./plans-filter.tpl"}
    </div>
    <div class="col-md-12">
        {include file="./multivendor.tpl"}
    </div>
    {hook h="displayMobbexCategorySettings" id="$id"}
</div>