// This javascript file is included in the front office

// Execute Embed Checkout
function getOptions() {
  return {
    id: mbbx.checkoutId,
    type: 'checkout',
    onResult: (data) => {
      var status = data.status.code;
      var link = mbbx.returnUrl + '&status=' + status + '&transactionId=' + data.id;

      window.top.location.href = link;
    },
    onClose: (cancelled) => {
      // Only if cancelled
      if (cancelled === true) {
        location.reload();
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
      if (data.result) {
        let status = data.data.status.code;
        let link = returnUrl + '&status=' + status + '&type=card' + '&transactionId=' + data.data.id;
        setTimeout(function(){window.top.location.href = link}, 5000)
      }
      else {
        alert("Error procesando el pago")
        unlockForm()
      }
    })
    .catch(error => {
      alert("Error: " + error)
      unlockForm()
    })
}

function isNewCard() {
  let cards = document.getElementsByName('walletCard');

  for (const card of cards) {
    if (card.checked)
      return card.value == 'newCard';
  }

  // Returns true if none is selected
  return true;
}

function renderLock() {
  let loaderModal = document.createElement("div")
  loaderModal.id = "mbbx-loader-modal"
  loaderModal.style.display = "none"

  let spinner = document.createElement("div")
  spinner.id = "mbbx-spinner"

  loaderModal.appendChild(spinner)

  document.body.appendChild(loaderModal)
}

function lockForm() {
  document.getElementById("mbbx-loader-modal").style.display = 'block'
}

function unlockForm() {
  document.getElementById("mbbx-loader-modal").style.display = 'none'
}

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

function executePayment() {
  if (mbbx.wallet && !isNewCard()) {
      executeWallet(mbbx.returnUrl);
  } else {
    if (mbbx.embed) {
      var mbbxButton = window.MobbexEmbed.init(getOptions());
      mbbxButton.open();
    } else {
      window.top.location.href = mbbx.checkoutUrl;
    }
  }
  return false;
};

window.addEventListener('load', function () {
  if (!window.mbbx)
    return false;

  renderLock();

  // If it is prestashop 1.7
  if (window.prestashop) {
    document.forms['mobbex_checkout'].onsubmit = function() {
      return executePayment();
    }

    document.querySelectorAll('.walletForm').forEach(form => {
      form.onsubmit = function (e) {
        activeCard(e.target.attributes.card.value);
        return executePayment();
    }
    });
  } else {
    document.querySelector('#mbbx-anchor').onclick = function() {
      activeCard(null);
      return executePayment();
    }

    document.querySelectorAll("#mobbexExecute").forEach(button => {
      button.onclick = function() {
        return executePayment();
      }
    });
  }
})