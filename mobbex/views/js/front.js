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

// Remove HTML entities 
function htmlDecode(input) {
  var doc = new DOMParser().parseFromString(input, "text/html");
  return doc.documentElement.textContent;
}

function renderOptions() {
  let child = document.createElement("div")
  let mobbexWallet = document.getElementById("mobbexWallet")
  child.classList += "payment-option"
  child.style.margin = "1.5rem 0"
  child.innerHTML = `
<span class="custom-radio float-xs-left">
  <input type="radio" name="walletCard" id="newCard" value="newCard" class="ps-shown-by-js">
  <span></span>
</span>
<label for="newCard" id="newCardLabel">
  Utilizar otra tarjeta / Medio de pago
</label>
`
  mobbexWallet.appendChild(child)
  document.getElementById("newCard").checked = true
  document.getElementById("newCard").addEventListener("click", function () {
    let forms = document.getElementsByClassName("walletForm")
    for (let i = 0; i < forms.length; i++) { forms[i].style.display = "none" }
  })
}

function renderWallet(wallet, width) {
  wallet.forEach((card, index) => {
    let mobbexWallet = document.getElementById("mobbexWallet")
    let child = document.createElement("div")
    let installments = card.installments
    let i = index
    child.classList += "payment-option"
    child.style.margin = "1.5rem 0"
    child.innerHTML = `
  <span class="custom-radio float-xs-left">
    <input type="radio" name="walletCard" id="card${index}" value="${index}" class="ps-shown-by-js">
    <span></span>
  </span>
  <label for="card${index}">
    <img width="30" style="border-radius: 1rem;margin: 0px 4px 0px 0px;" src="${card.source.card.product.logo}"> ${card.card.card_number}
  </label>
  <div id="card_${index}_form" class="walletForm additional-information form-group" style="display: none;">
    <input class="form-control" type="password" name="securityCode" placeholder="${card.source.card.product.code.name}" maxlength="${card.source.card.product.code.length}">
    <select class="form-control form-control-select" name="installment"></select>
    <input type="hidden" name="intentToken" value="${card.it}">
  </div>`
  
    mobbexWallet.appendChild(child)

    document.getElementById(`card${index}`).addEventListener("click", function () {
      let forms = document.getElementsByClassName("walletForm")
      for (let i = 0; i < forms.length; i++) { forms[i].style.display = "none" }
      document.getElementById(`card_${index}_form`).style.display = "block"
    })

    let cardDiv = document.getElementById(`card_${index}_form`)
    cardDiv.style.margin = '0 2rem'
    cardDiv.getElementsByTagName("input")[0].style.margin = '1rem 0'

    if (width) { cardDiv.getElementsByTagName("input")[0].style.maxWidth = '184px' }

    installments.forEach(installment => {
      let div = document.getElementById(`card_${i}_form`)
      let select = div.getElementsByTagName("select")[0]
      let option = document.createElement("option")
      option.value = installment.reference
      option.text = installment.name
      select.appendChild(option)
    })
  })
}

function renderNoCardsMessage() {
  //let child = document.createElement("div")
  let mobbexWallet = document.getElementById("mobbexWallet")
  document.getElementById("newCardLabel").innerHTML = "Pagar con Mobbex"
  mobbexWallet.style.display = "none"
  /* child.classList += "payment-option"
  child.innerHTML = '<p>No hay tarjetas disponibles</p>'
  child.style.margin = "1.5rem 0"
  mobbexWallet.appendChild(child) */
}

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

  for (const form of forms)
    form.style.display = form.id == `card_${cardId}_form` ? 'block' : 'none';

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

    renderOptions();

    if (mbbx.wallet) {
      renderWallet(mbbx.wallet);
    } else {
      renderNoCardsMessage();
    }
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