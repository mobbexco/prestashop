{if $ps_version === '1.6'}
  <div class="row">
    <div class="col-xs-12 col-md-12">
      <p class="payment_module">
        <a id="mbbx-anchor" href="#">
          {$payment_label|escape:'htmlall':'UTF-8'}
        </a>
      </p>
    </div>
  </div>
{/if}

<form id="mobbex_checkout" class="mobbex-checkout-form" method="post" action="{$checkout_url|escape:'html':'UTF-8'}"></form>
<div id="mbbx-container"></div>

{literal}
  <script type="text/javascript">
    var script = document.createElement('script');
    script.src = `https://res.mobbex.com/js/embed/mobbex.embed@1.0.8.js?t=${Date.now()}`;
    script.async = true;
    document.body.appendChild(script);
    
    // Remove HTML entities 
    function htmlDecode(input) 
    {
      var doc = new DOMParser().parseFromString(input, "text/html");
      return doc.documentElement.textContent;
    }

    // Get type from status 
    function getType(status)
    {
      if(status < 2) {
        return "none";
      } else if (status == 2) {
        return "cash";
      } else if (status == 3 || status == 4 || status >= 200 && status < 400) {
        return "card";
      }
    }
    
    // Get checkout data from php
    var checkoutUrl = htmlDecode('{/literal}{$checkout_url}{literal}');
    var checkoutId = '{/literal}{$checkout_id}{literal}';

    var options = {
      id: checkoutId,
      type: 'checkout',
        onResult: (data) => {
          window.MobbexEmbed.close();
        },
        onPayment: (data) => {
          var status = data.data.status.code;
          var link   = checkoutUrl + '&status=' + status + '&type=' + getType(status) + '&transactionId=' + data.data.id;

          // If order status is not recoverable
          if (! ((status >= 400 && status <= 500 && status != 401 && status != 402) || status == 0) ) {
            // Redirect
            setTimeout(function () {
              window.top.location.href = link;
            }, 5000);
          }
        },
        onOpen: () => {
          // Do nothing
        },
        onClose: (cancelled) => {
          // Only if cancelled
          if (cancelled === true) {
            location.reload();
          }
        },
        onError: (error) => {
          // Do nothing
        }
    }

    {/literal}
    {if $ps_version === '1.6'}
      document.querySelector('#mbbx-anchor').onclick =
    {else}
      document.forms['mobbex_checkout'].onsubmit =
    {/if}
    {literal}
      function () {
        var mbbxButton = window.MobbexEmbed.init(options);
        mbbxButton.open();
        return false;
      };

  </script>
{/literal}