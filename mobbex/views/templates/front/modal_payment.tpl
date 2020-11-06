{if $ps_version === '1.6'}
  <div class="row">
    <div class="col-xs-12 col-md-12">
      <p class="payment_module">
        {if $is_wallet}
        <a href="{$link->getModuleLink('mobbex', 'wallet', [], true)|escape:'htmlall':'UTF-8'}">{$payment_label|escape:'htmlall':'UTF-8'}</a>
        {else}
        <a id="mbbx-anchor" href="#">
          {$payment_label|escape:'htmlall':'UTF-8'}
        </a>
        {/if}
      </p>
    </div>
  </div>
{/if}

<div id="mobbexWallet" class="additional-information"></div>
<form id="mobbex_checkout" class="mobbex-checkout-form" method="post" action="{$checkout_url|escape:'html':'UTF-8'}"></form>
<div id="mbbx-container"></div>

{literal}
  <script type="text/javascript" src="../modules/mobbex/views/js/front.js"></script>
  <script type="text/javascript" id="mobbexScript">  
    var script = document.createElement('script');
    script.src = `https://res.mobbex.com/js/embed/mobbex.embed@1.0.17.js?t=${Date.now()}`;
    script.async = true;
    document.body.appendChild(script);

    var walletScript = document.createElement('script')
    walletScript.src = "https://res.mobbex.com/js/sdk/mobbex@1.0.0.js"
    walletScript.type = "text/javascript"
    document.body.appendChild(walletScript)

    var stylesheet = document.createElement('link')
    stylesheet.rel = 'stylesheet'
    stylesheet.href = '../modules/mobbex/views/css/front.css'
    document.head.appendChild(stylesheet)

    renderLock()

    // Get checkout data from php
    var checkoutUrl = htmlDecode('{/literal}{$checkout_url}{literal}');
    var checkoutId = '{/literal}{$checkout_id}{literal}';

    var options = getOptions(checkoutUrl, checkoutId)

    var isWallet = {/literal}{if $is_wallet}{$is_wallet}{else}0{/if}{literal}
    
    {/literal}
    {if $ps_version === '1.6'}
    {literal}
      if (isWallet === 0) {
        document.querySelector('#mbbx-anchor').onclick = function(){executePayment()}    
      }
    {/literal}
    {else}
      {literal}
      document.forms['mobbex_checkout'].onsubmit = function(){return executePayment()}
      if (isWallet === 1) {
        renderOptions()
        var walletJson = '{/literal}{$wallet}{literal}'
        if (walletJson === '' || typeof walletJson === 'undefined') renederNoCardsMessage()
        else renderWallet(JSON.parse(walletJson.replace(/&quot;/g,'"')))
      }
      {/literal}
    {/if}
    {literal}
    function executePayment() {
      if (isWallet === 1) {
        if(isNewCard()){
          var mbbxButton = window.MobbexEmbed.init(options);
          mbbxButton.open();
        }
        else executeWallet(checkoutUrl)
      }
      else {
        var mbbxButton = window.MobbexEmbed.init(options);
        mbbxButton.open();
      }
      return false;
    };
  </script>
{/literal}