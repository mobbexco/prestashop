{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='mobbex'}">{l s='Checkout' mod='mobbex'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Mobbex Wallet payment' mod='mobbex'}
{/capture}

<h1 class="page-heading">{l s='Order summary' mod='mobbex'}</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

<div class="box">
    <h3 class="page-subheading">{l s='Mobbex Wallet payment' mod='mobbex'}</h3>
    <form action="#" method="post" id="walletForm">
        <h4 style="margin-top:20px;">
            {l s='The total amount of your order is' mod='mobbex'}
            <span id="amount" class="price">{displayPrice price=$total}</span>
            {if $use_taxes == 1}
            {l s='(tax incl.)' mod='mobbex'}
            {/if}
        </h4>
    </form>
    <div id="mobbexWallet"></div>
</div>
<p class="cart_navigation clearfix" id="cart_navigation">
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}" class="button-exclusive btn btn-default">
        <i class="icon-chevron-left"></i>
        {l s='Other payment methods' mod='mobbex'}
    </a>
    <button type="submit" id="mobbexExecute" class="button btn btn-default button-medium">
        <span>
            {l s='I confirm my order' mod='mobbex'}
            <i class="icon-chevron-right right"></i>
        </span>
    </button>
</p>

<div id="mbbx-container"></div>

{literal}
<script type="text/javascript" src="/modules/mobbex/views/js/front.js"></script>
<script type="text/javascript">
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
    stylesheet.href = '/modules/mobbex/views/css/front.css'
    document.head.appendChild(stylesheet)

    renderLock()

    renderOptions()

    var walletJson = '{/literal}{$wallet}{literal}'
    if (walletJson === '' || typeof walletJson === 'undefined') renederNoCardsMessage()
    else renderWallet(JSON.parse(walletJson.replace(/&quot;/g, '"')), true)

    let cards = document.getElementsByName("walletCard")

    var isWallet = {/literal}{if $is_wallet}{$is_wallet}{else}0{/if}{literal}

    var checkoutUrl = htmlDecode('{/literal}{$checkout_url}{literal}');
    var checkoutId = '{/literal}{$checkout_id}{literal}';

    var options = getOptions(checkoutUrl, checkoutId)

    document.querySelector("#mobbexExecute").onclick = function(){executePayment()}

    function executePayment() {
      if (isWallet === 1) {
          if (isNewCard()) {
              var mbbxButton = window.MobbexEmbed.init(options);
              mbbxButton.open();
          }
          else executeWallet(checkoutUrl)
            
      } else {
          var mbbxButton = window.MobbexEmbed.init(options);
          mbbxButton.open();
      }
      return false;
  }
</script>
{/literal}