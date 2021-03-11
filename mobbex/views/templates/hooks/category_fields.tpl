<div class="form-group">
    <div class="row">
      <label class="control-label col-lg-3">
      <span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="Forbidden characters <>;=#{}">
        {l s='Elija los planes que NO quiera que aparezcan durante la compra' mod='mobbex'}
      </span>
      </label>
    </div>
    <div class="col-lg-9">							
      <div class="form-group">	
        {foreach item=item key=key from=$ahora}
        <div class="row" style="margin-left: 35%;">
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
    </div>
</div>