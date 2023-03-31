window.addEventListener("load", function () {

  window.onpopstate = function (event) {
    window.top.location.href = mbbx.return;
  };

  /**
   * Open the Mobbex checkout modal.
   *
   * @param {array} response Mobbex checkout or subscriber response.
   */
  function openCheckoutModal(response) {
    unlockForm();

    let options = {
      id: response.data.id,
      type: response.data.sid ? "subscriber_source" : "checkout",
      paymentMethod: mbbx.method || null,
      onResult: (data) => {
        var status = data.status.code;

        if (status > 1 && status < 400) {
          window.top.location.href =
            response.data.return_url +
            "&status=" +
            status +
            "&transactionId=" +
            data.id;
        } else {
          window.top.location.href = mbbx.return;
        }
      },
      onClose: (cancelled) => {
        // Only if cancelled
        if (cancelled === true) {
          window.top.location.href = mbbx.return;
        }
      },
    };

    if (response.data.sid) options.sid = response.data.sid;

    let mobbexEmbed = window.MobbexEmbed.init(options);
    mobbexEmbed.open();
  }

  /**
   * Redirect to Mobbex checkout page.
   *
   * @param {array} response Mobbex checkout or subscriber response.
   */
  function redirectToCheckout(response) {
    window.top.location.href =
      response.data.url + (mbbx.method ? "?paymentMethod=" + mbbx.method : "");
  }

  /**
   * Create checkout|subscriber and process the order if needed.
   *
   * @param {CallableFunction} callback
   */
  function processPayment(callback) {
    lockForm();

    $.ajax({
      dataType: "json",
      method: "POST",
      url: mbbx.paymentUrl,

      success: (response) => {
        if (response.order) {
          callback(response.data ? response : mbbx);
        } else if (mbbx.errorUrl) {
          window.top.location = mbbx.errorUrl;
        } else {
          window.top.location.reload();
        }
      },
      error: () => {
        window.top.location.reload();
      },
    });
  }

  /**
   * Execute wallet payment from selected card.
   *
   * @param {array} response Mobbex checkout response.
   */
  function executeWallet(response) {
    let cardNumber = $(`#card-${mbbx.card}-number`).val();
    let updatedCard = response.data.wallet.find(
      (card) => card.card.card_number == cardNumber
    );

    var options = {
      intentToken: updatedCard.it,
      installment: $(`#card-${mbbx.card}-installments`).val(),
      securityCode: $(`#card-${mbbx.card}-code`).val(),
    };

    // Execute operation
    window.MobbexJS.operation
      .process(options)
      .then((data) => {
        let status = data.result ? data.data.status.code : 0;

        if (status > 1 && status < 400) {
          setTimeout(function () {
            window.top.location.href =
              response.data.return_url +
              "&status=" +
              status +
              "&transactionId=" +
              data.data.id;
          }, 5000);
        } else {
          alert("Error procesando el pago");
          unlockForm();
        }
      })
      .catch((error) => alert("Error: " + error) || unlockForm());
  }

  /**
   * Render form loader element.
   */
  function renderLock() {
    let loaderModal = document.createElement("div");
    loaderModal.id = "mbbx-loader-modal";
    loaderModal.style.display = "none";

    let spinner = document.createElement("div");
    spinner.id = "mbbx-spinner";

    loaderModal.appendChild(spinner);

    document.body.appendChild(loaderModal);
  }

  /**
   * Enable loader and lock form.
   */
  function lockForm() {
    document.getElementById("mbbx-loader-modal").style.display = "block";
  }

  /**
   * Disable loader and unlock form.
   */
  function unlockForm() {
    document.getElementById("mbbx-loader-modal").style.display = "none";
  }

  /**
   * Set method/card selected in mbbx global var. Use only in jQuery events.
   *
   * @param {Element} method Current method element.
   */
  function setCurrentMethod(method) {
    mbbx.card = $(method).attr("card");
    mbbx.method = $(method).attr("group");

    // Only for ps 1.6. In ps 1.7 forms are natively hidden
    if (!window.prestashop) hideCardForms();
  }

  /**
   * Hide unchecked card options for ps 1.6.
   */
  function hideCardForms() {
    $(".walletForm").each(function (i, form) {
      $(form).css(
        "display",
        $(form).attr("card") == mbbx.card ? "block" : "none"
      );
    });
  }

  /**
   * Validate checked card form fields.
   */
  function validateCardForm() {
    let securityCode = $(`#card-${mbbx.card}-code`);

    // Validate security code field length
    if (securityCode.val().length < parseInt(securityCode.attr("maxlength"))) {
      securityCode.css("borderColor", "#dc3545");
      return alert("Código de seguridad incompleto");
    }

    return true;
  }

  /**
   * Execute mobbex payment.
   */
  function executePayment() {
    if (mbbx.card && !validateCardForm()) return;

    processPayment((response) => {
      if (mbbx.card) executeWallet(response);
      else if (mbbx.embed) openCheckoutModal(response);
      else redirectToCheckout(response);
    });
  }

  function renderEmbedContainer() {
    var container = document.createElement("div");
    container.id = "mbbx-container";

    // Insert after body
    document.body.prepend(container);
  }

  renderLock();
  renderEmbedContainer();

  // Use jquery to listen checkout events before ajax calls end (for onepage plugins support)
  $(document).on(
    window.prestashop ? "submit" : "click",
    ".mbbx-method",
    function (e) {
      return e.preventDefault() || setCurrentMethod(this) || executePayment();
    }
  );

  // Add wallet payment events
  if (window.prestashop) {
    $(document).on("submit", ".walletForm", function (e) {
      return e.preventDefault() || setCurrentMethod(this) || executePayment();
    });
  } else {
    $(document).on("click", ".walletAnchor", function (e) {
      return e.preventDefault() || setCurrentMethod(this);
    });
    $(document).on("click", "#mobbexExecute", () => executePayment());
  }
});