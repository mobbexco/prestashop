<div class="form-group">
    <div class="row">
      <div class="col-md-12">
        <h3>{l s='Elija los planes que NO quiera que aparezcan durante la compra' mod='mobbex'}</h3>
      </div>
    </div>
    {foreach item=item key=key from=$ahora}
    <div class="row" style="margin-left: 15px;">
      <div class="col-md-8 form-group">
        <div class="checkbox">                          
          <label style="font-size: 16px;padding-left: 10px;">
            <input type="checkbox" id="{$key}" name="{$key}" {if !empty($item['data'] && $item['data'] == 'yes')}checked="checked"{/if}>
            {$item['label']}
          </label>
        </div>
      </div>
    </div>
    {/foreach}
</div>