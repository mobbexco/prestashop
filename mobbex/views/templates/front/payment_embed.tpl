
<script type="text/javascript" class="nose">
  var checkoutId = {checkout_id};
  {literal}

  var script = document.createElement('script');
  script.src = `https://res.mobbex.com/js/embed/mobbex.embed@1.0.8.js?t=${Date.now()}`;
  script.async = true;
  script.addEventListener('load', () => {
    // Realizá la acción que sea necesaria aca :)
    renderMobbexButton();
    initMobbexPayment();
  });
  console.log(checkout_id);
  document.body.appendChild(script);
  {literal}
    var options = {
      id: checkoutId,
      type: 'checkout',
        onResult: (data) => {
          // OnResult es llamado cuando se toca el Botón Cerrar
          window.MobbexEmbed.close();
        },
        onPayment: (data) => {
          console.info('Payment: ', data);
        },
        onOpen: () => {
          console.info('Pago iniciado.');
        },
        onClose: (cancelled) => {
          console.info(`${cancelled ? 'Cancelado' : 'Cerrado'}`);
        },
        onError: (error) => {
          console.error("ERROR: ", error);
        }
    }
    
  {/literal}
</script>

  <div id="mbbx-button"></div>