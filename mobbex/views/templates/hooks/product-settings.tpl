<div class="{if constant('_PS_VERSION_') < '1.7'}row panel product-tab{else}translations tabbable{/if}">
    <div class="translationsFields tab-content">
        <div class="form-group">
            <h2>Cofiguración de planes</h2>
            <p class="subtitle">Active los planes que desee que aparezcan en el checkout</p>
        </div>
        <div class="form-group">
            {include file="./plans-filter.tpl"}
        </div>
        <div class="form-group">
            <h2>Multivendedor</h2>
            <p class="subtitle">Coloque el UID de la entidad seleccionada para el producto.</p>
        </div>
        <div class="form-group">
            {include file="./multivendor.tpl"}
        </div>
        {hook h="displayMobbexProductSettings" id="$id"}
        {if constant('_PS_VERSION_') < '1.7'}
            <div class="panel-footer">
                <a href="{$link->getAdminLink('AdminProducts')|escape:'html':'UTF-8'}{if isset($smarty.request.page) && $smarty.request.page > 1}&amp;submitFilterproduct={$smarty.request.page|intval}{/if}" class="btn btn-default"><i class="process-icon-cancel"></i> {l s='Cancel'}</a>
                <button type="submit" name="submitAddproduct" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> {l s='Save'}</button>
                <button type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> {l s='Save and stay'}</button>
            </div>
        {/if}
    </div>
</div>