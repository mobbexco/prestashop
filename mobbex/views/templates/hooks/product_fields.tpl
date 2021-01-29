{if $ps_version === '1.6'}
  <div class="panel product-tab">
{else}
  <div class="translations tabbable">
{/if}
  <div class="translationsFields tab-content">
    <div class="row">
      <div class="col-md-12">
        <h3>{l s='Planes comunes y pre-armados: Habilite para que NO aparezcan en el checkout de este producto.' mod='mobbex'}</h3>
      </div>
    </div>
    {* Render common plans fields *}
    {foreach from=$commonPlansFields item=item key=key}
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
    {* Render advanced plans fields *}
    {assign var='sourcesRendered' value=array()}
    {foreach from=$advancedPlansFields item=item key=key name=name}
      {if empty($sourcesRendered)}
        <h3>{l s='Planes con reglas avanzadas: Habilite para que aparezcan en el checkout de este producto.' mod='mobbex'}</h3>
      {/if}
      {* Sources *}
      {if !in_array($item.sourceName, $sourcesRendered)}
        <div class="row" style="margin-left: 15px">
          <div class="form-group" style="display: flex; align-items: center;">
            <img src="https://res.mobbex.com/images/sources/{$item['sourceRef']}.png" style="border-radius: 100%; width: 40px;">
            <h3 style="margin: 0; padding-left: 10px; font-weight: 600;">{$item['sourceName']}</h3>
          </div>
        </div>
        {append var='sourcesRendered' value=$item.sourceName}
      {/if}
      {* Checkboxes *}
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
</div>