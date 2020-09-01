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
      } else if (status == 2 || status == 3) {
        return "cash";
      } else if (status == 4 || status >= 200 && status < 400) {
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
          setTimeout(function () {
            window.top.location.href = link;
          }, 5000)
        },
        onOpen: () => {
          // Do nothing
        },
        onClose: (cancelled) => {
          // Do nothing
        },
        onError: (error) => {
          // Do nothing
        }
    }

    document.forms['mobbex_checkout'].onsubmit = function () { 
        var mbbxButton = window.MobbexEmbed.init(options);
        mbbxButton.open();
        return false;
    }

  </script>
{/literal}