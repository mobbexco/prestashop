(function (window, $) {
  /**
   * Open the Mobbex checkout modal.
   * 
   * @param {array} response Mobbex checkout or subscriber response.
   */
  function openCheckoutModal(response) {
    let options = {
      id: response.data.id,
      type: response.data.sid ? 'subscriber_source' : 'checkout',
      paymentMethod: mbbx.paymentMethod || null,
      onResult: (data) => {
        var status = data.status.code;

        if (status > 1 && status < 400) {
          window.top.location.href = response.data.returnUrl + '&status=' + status + '&transactionId=' + data.id;
        } else {
          window.top.location.reload();
        }
      },
      onClose: (cancelled) => {
        // Only if cancelled
        if (cancelled === true) {
          window.top.location.reload();
        }
      }
    };

    if (response.data.sid)
      options.sid = response.data.sid;

    let mobbexEmbed = window.MobbexEmbed.init(options);
    mobbexEmbed.open();
  }

  /**
   * Redirect to Mobbex checkout page.
   * 
   * @param {array} response Mobbex checkout or subscriber response.
   */
  function redirectToCheckout(response) {
    window.top.location.href = response.data.url + (mbbx.paymentMethod ? '?paymentMethod=' + mbbx.paymentMethod : '');
  }

  /**
   * Create checkout|subscriber and process the order if needed.
   * 
   * @param {CallableFunction} callback
   */
  function processPayment(callback) {
    $.ajax({
      dataType: 'json',
      method: 'POST',
      url: mbbx.paymentUrl,

      success: (response) => {
          unlockForm();

          if (response.data && response.order) {
            callback(response);
          } else if (mbbx.errorUrl) {
            window.top.location = mbbx.errorUrl;
          } else {
            window.top.location.reload();
          }
      },
      error: () => {
        window.top.location.reload();
      }
    });
  }

  /**
   * Execute wallet payment from selected card.
   * 
   * @param {array} response Mobbex checkout response.
   */
  function executeWallet(response) {
    let card        = $('[name=walletCard][checked=checked]').val() ?? null;
    let cardNumber  = $(`#card-${card}-number`).val();
    let updatedCard = response.data.wallet.find(card => card.card.card_number == cardNumber);

    var options = {
        intentToken: updatedCard.it,
        installment: $(`#card-${card}-installments`).val(),
        securityCode: $(`#card-${card}-code`).val()
    };

    // Validate security code
    if (options.securityCode.length < parseInt($(`#card-${card}-code`).attr('maxlength'))) {
      $(`#card-${card}-code`).style.borderColor = '#dc3545';
      return alert("Código de seguridad incompleto") && unlockForm();
    }

    // Execute operation
    window.MobbexJS.operation.process(options).then(data => {
      let status = data.result ? data.data.status.code : 0;

      if (status > 1 && status < 400) {
        setTimeout(function(){
          window.top.location.href = returnUrl + '&status=' + status + '&transactionId=' + data.data.id;
        }, 5000);
      } else {
        alert('Error procesando el pago')
        unlockForm()
      }
    }).catch(error => alert('Error: ' + error) && unlockForm());
  }

/**
 * Return true if a card is current selected.
 */
function isCardSelected() {
  return $('[name=walletCard][checked=checked]').length > 0;
}

/**
 * Render form loader element.
 */
function renderLock() {
  let loaderModal = document.createElement("div")
  loaderModal.id = "mbbx-loader-modal"
  loaderModal.style.display = "none"

  let spinner = document.createElement("div")
  spinner.id = "mbbx-spinner"

  loaderModal.appendChild(spinner)

  document.body.appendChild(loaderModal)
}

/**
 * Enable loader and lock form.
 */
function lockForm() {
  document.getElementById("mbbx-loader-modal").style.display = 'block'
}

/**
 * Disable loader and unlock form.
 */
function unlockForm() {
  document.getElementById("mbbx-loader-modal").style.display = 'none'
}

/**
 * Active a wallet card by card key.
 * 
 * @param {number} cardId 
 */
function activeCard(cardId) {
  let cards = $('[name=walletCard]');
  let forms = $('.walletForm]');

  for (const card of cards)
    card.attr('checked', card.value == cardId);

  // Only for ps 1.6. In ps 1.7 forms are natively hidden
  if (!window.prestashop) {
    for (const form of forms)
      form.style.display = form.id == `card_${cardId}_form` ? 'block' : 'none';
  }

  return false;
}

/**
 * Execute mobbex payment.
 */
function executePayment() {
  lockForm();

  if (isCardSelected()) {
    processPayment(response => executeWallet(response));
  } else {
    processPayment(response => mbbx.embed ? openCheckoutModal(response) : redirectToCheckout(response));
  }
  return false;
};

function renderEmbedContainer() {
  var container = document.createElement('div');
  container.id  = 'mbbx-container';

  // Insert after body
  document.body.prepend(container);
}

window.addEventListener('load', function () {
  renderLock();
  renderEmbedContainer();

  // Use jquery to listen checkout events before ajax calls end (for onepage plugins support)
  $(document).on(window.prestashop ? 'submit' : 'click', '.mbbx-method', function (e) {
    e.preventDefault();
    activeCard(null);
    mbbx.paymentMethod = $(this).attr('group');
    return executePayment();
  });

  // If it is prestashop 1.7
  if (window.prestashop) {
    $(document).on('submit', '.walletForm', function (e) {
      e.preventDefault();
      activeCard($(this).attr('card'));
      return executePayment();
    });
  } else {
    $(document).on('click', '.walletAnchor', function () {
      return activeCard($(this).attr('card'));
    });

    $(document).on('click', '#mobbexExecute', function () {
      return executePayment();
    });
  }
});
}) (window, jQuery);