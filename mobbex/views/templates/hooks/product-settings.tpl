<div class="{if constant('_PS_VERSION_') < '1.7'}row panel product-tab{else}translations tabbable{/if}">
    <div class="translationsFields tab-content">
        <div class="col-md-12">
            <h2>Cofiguración de planes</h2>
            <p class="subtitle">Active los planes que desee que aparezcan en el checkout</p>
        </div>
        <div class="col-md-12">
            {include file="./plans-filter.tpl"}
        </div>
    </div>
</div>