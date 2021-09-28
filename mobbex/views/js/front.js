(function (window, $) {
/**
 * Get embed checkout options.
 * 
 * @returns {object}
 */
function getOptions() {
  return {
    id: mbbx.checkoutId,
    type: 'checkout',
    paymentMethod: mbbx.paymentMethod || null,
    onResult: (data) => {
      var status = data.status.code;

      if (status > 1 && status < 400) {
        window.top.location.href = mbbx.returnUrl + '&status=' + status + '&transactionId=' + data.id;
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
  }
}

/**
 * Execute wallet payment.
 * 
 * @param {string} returnUrl 
 */
function executeWallet(returnUrl) {
  lockForm()
  let securityCode;
  let installment;
  let intentToken;
  let cards = document.getElementsByName("walletCard")
  for (let i = 0; i < cards.length; i++) {
    if (cards[i].checked) {
      let cardIndex = cards[i].value
      let cardDiv = document.getElementById(`card_${cardIndex}_form`)
      securityCode = cardDiv.getElementsByTagName("input")[0].value
      maxlength = cardDiv.getElementsByTagName("input")[0].getAttribute('maxlength')
      if (securityCode.length < parseInt(maxlength)) {
        cardDiv.getElementsByTagName("input")[0].style.borderColor = '#dc3545'
        unlockForm()
        return alert("Código de seguridad incompleto")
      }
      installment = cardDiv.getElementsByTagName("select")[0].value
      intentToken = cardDiv.getElementsByTagName("input")[1].value
    }
  }
  window.MobbexJS.operation.process({
    intentToken: intentToken,
    installment: installment,
    securityCode: securityCode
  })
    .then(data => {
      let status = data.result ? data.data.status.code : 0;

      if (status > 1 && status < 400) {
        setTimeout(function(){
          window.top.location.href = returnUrl + '&status=' + status + '&type=card' + '&transactionId=' + data.data.id;
        }, 5000);
      } else {
        alert('Error procesando el pago')
        unlockForm()
      }
    })
    .catch(error => {
      alert("Error: " + error)
      unlockForm()
    })
}

/**
 * Check if new card option is selected.
 */
function isNewCard() {
  let cards = document.getElementsByName('walletCard');

  for (const card of cards) {
    if (card.checked)
      return card.value == 'newCard';
  }

  // Returns true if none is selected
  return true;
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
  let cards = document.getElementsByName('walletCard');
  let forms = document.getElementsByClassName('walletForm');

  for (const card of cards)
    card.checked = card.value == cardId;

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
  if (mbbx.wallet && !isNewCard()) {
      executeWallet(mbbx.returnUrl);
  } else {
    if (mbbx.embed) {
      var mbbxButton = window.MobbexEmbed.init(getOptions());
      mbbxButton.open();
    } else {
      window.top.location.href = mbbx.checkoutUrl + (mbbx.paymentMethod ? '?paymentMethod=' + mbbx.paymentMethod : '');
    }
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