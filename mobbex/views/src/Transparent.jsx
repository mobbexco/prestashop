import React, { useState, useEffect } from "react";
import ReactDOM from "react-dom";

/**
 * Main Transparent Form Component
 */
const TransparentForm = ({ config }) => {
  // Form states
  const [cardNumber, setCardNumber] = useState("");
  const [cardName, setCardName] = useState("");
  const [cardDni, setCardDni] = useState("");
  const [cardExpiration, setCardExpiration] = useState("");
  const [securityCode, setSecurityCode] = useState("");
  const [installments, setInstallments] = useState([]);
  const [selectedInstallment, setSelectedInstallment] = useState("");

  // UI states
  const [isDetecting, setIsDetecting] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);
  const [errors, setErrors] = useState({});
  const [detectedSource, setDetectedSource] = useState(null);
  const [availableCardTypes, setAvailableCardTypes] = useState([]);

  // Config from PrestaShop
  const {
    intentToken,
    detectUrl,
    sourcesUrl,
    processUrl,
    description,
    showBanner,
    i18n = {},
  } = config;
  const detectEndpoint = detectUrl || config.ajaxUrl;
  const sourcesEndpoint = sourcesUrl || config.sourcesUrl;

  useEffect(() => {
    const fetchSources = async () => {
      if (!sourcesEndpoint) {
        return;
      }

      try {
        const res = await fetch(sourcesEndpoint);

        if (!res.ok) {
          console.error("[Mobbex] Error fetching payment sources:", res);
          return;
        }

        const data = await res.json();
        if (!data || !Array.isArray(data.sources)) {
          console.error(
            "[Mobbex] Invalid data format for payment sources:",
            data,
          );
          return;
        }

        const filtered = data.sources.filter(
          (source) =>
            source?.view?.group === "card" && source?.installments?.enabled,
        );

        const cardTypes = filtered
          .map((source) => source?.source?.reference || source?.source)
          .filter(Boolean)
          .slice(0, 4);

        setAvailableCardTypes(cardTypes);
      } catch (error) {
        console.error("[Mobbex] Error loading card brands:", error);
      }
    };

    fetchSources();
  }, [sourcesEndpoint]);

  /**
   * Detect card source when BIN is entered
   */
  useEffect(() => {
    const detectSource = async () => {
      const cleanNumber = cardNumber.replace(/\s/g, "");

      if (cleanNumber.length >= 6) {
        setIsDetecting(true);

        try {
          const bin = cleanNumber.substring(0, 6);

          const formData = new FormData();
          formData.append("action", "detectCard");
          formData.append("bin", bin);
          formData.append("token", intentToken);

          const res = await fetch(detectEndpoint, {
            method: "POST",
            body: formData,
          });

          if (!res.ok) {
            throw new Error(`Network error: ${res.statusText}`);
          }

          const data = await res.json();
          const payload = data.data || data;
          const availableInstallments = payload.installments || [];

          if (data.result && availableInstallments.length) {
            setDetectedSource(payload.card || payload);
            setInstallments(availableInstallments);

            if (availableInstallments.length === 1) {
              setSelectedInstallment(availableInstallments[0].reference);
            }

            clearError("cardNumber");
          } else {
            setErrors((prev) => ({
              ...prev,
              cardNumber:
                i18n.no_installments ||
                "No se encontraron cuotas para esta tarjeta",
            }));
            setInstallments([]);
          }
        } catch (error) {
          console.error("[Mobbex] Error detecting card:", error);
          setErrors((prev) => ({
            ...prev,
            cardNumber: i18n.detect_error || "Error al detectar medio de pago",
          }));
        } finally {
          setIsDetecting(false);
        }
      } else {
        setInstallments([]);
        setSelectedInstallment("");
        setDetectedSource(null);
      }
    };

    const timer = setTimeout(detectSource, 500);
    return () => clearTimeout(timer);
  }, [cardNumber, intentToken, detectEndpoint, i18n]);

  const handleSubmit = (e, parentForm) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }

    if (!validateAllFields()) {
      setIsProcessing(false);
      return false;
    }

    if (!parentForm || !processUrl) {
      setErrors((prev) => ({
        ...prev,
        general: i18n.process_error || "Error al procesar el pago",
      }));
      setIsProcessing(false);
      return false;
    }

    setIsProcessing(true);

    const hiddenFields = parentForm.querySelectorAll(
      ".mobbex-transparent-field",
    );
    hiddenFields.forEach((field) => field.remove());

    const payload = {
      number: cardNumber.replace(/\s/g, ""),
      expiry: cardExpiration.replace(/\s/g, ""),
      cvv: securityCode,
      name: cardName.trim(),
      identification: cardDni,
      installments: selectedInstallment,
    };

    Object.entries(payload).forEach(([name, value]) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value;
      input.className = "mobbex-transparent-field";
      parentForm.appendChild(input);
    });

    parentForm.action = processUrl;
    parentForm.method = "POST";
    parentForm.submit();

    return true;
  };

  useEffect(() => {
    const root = document.getElementById("mobbex-transparent-root");
    const parentForm = root ? root.closest("form.transparentForm") : null;
    const confirmButton = document.querySelector(
      '#payment-confirmation button[type="submit"]',
    );

    if (!parentForm || !confirmButton) {
      console.error(
        "[Mobbex Transparent] Error finding parent form or confirm button",
      );
      return;
    }

    // avoid intercept another payment methods
    const isTransparentActive = () => {
      if (
        !root ||
        !parentForm ||
        !root.isConnected ||
        !parentForm.isConnected
      ) {
        return false;
      }

      const parentStyle = window.getComputedStyle(parentForm);
      if (
        parentStyle.display === "none" ||
        parentStyle.visibility === "hidden" ||
        parentForm.offsetParent === null
      ) {
        return false;
      }

      return true;
    };

    const onConfirmClick = (event) => {
      if (!isTransparentActive()) {
        return;
      }

      handleSubmit(event, parentForm);
    };

    confirmButton.addEventListener("click", onConfirmClick);

    return () => {
      confirmButton.removeEventListener("click", onConfirmClick);
    };
  }, [
    cardNumber,
    cardName,
    cardDni,
    cardExpiration,
    securityCode,
    selectedInstallment,
    processUrl,
  ]);

  /**
   * Validate all form fields
   */
  const validateAllFields = () => {
    const newErrors = {};
    let isValid = true;

    const cleanCardNumber = cardNumber.replace(/\s/g, "");
    if (!cleanCardNumber || !/^\d{13,19}$/.test(cleanCardNumber)) {
      newErrors.cardNumber = i18n.invalid_card || "Numero de tarjeta invalido";
      isValid = false;
    }

    if (!cardName || cardName.length < 3) {
      newErrors.cardName = i18n.invalid_name || "Nombre invalido";
      isValid = false;
    }

    if (!cardDni || !/^\d{7,15}$/.test(cardDni)) {
      newErrors.cardDni = i18n.invalid_dni || "DNI invalido";
      isValid = false;
    }

    const cleanExpiration = cardExpiration.replace(/\s/g, "");
    if (!cleanExpiration || !/^(0[1-9]|1[0-2])\/\d{2}$/.test(cleanExpiration)) {
      newErrors.cardExpiration =
        i18n.invalid_expiry || "Fecha de vencimiento invalida";
      isValid = false;
    }

    if (!securityCode || !/^\d{3,4}$/.test(securityCode)) {
      newErrors.securityCode = i18n.invalid_cvv || "CVV invalido";
      isValid = false;
    }

    if (!selectedInstallment) {
      newErrors.installments =
        i18n.select_installments || "Debe seleccionar las cuotas";
      isValid = false;
    }

    setErrors(newErrors);
    return isValid;
  };

  /**
   * Helper functions
   */
  const clearError = (field) => {
    setErrors((prev) => {
      const newErrors = { ...prev };
      delete newErrors[field];
      return newErrors;
    });
  };

  const formatCardNumber = (value) => {
    const cleaned = value.replace(/\s/g, "");
    const formatted = cleaned.match(/.{1,4}/g)?.join(" ") || cleaned;
    return formatted.substring(0, 19);
  };

  const formatExpiration = (value) => {
    let cleaned = value.replace(/\D/g, "");
    // If the year has 4 digits (e.g. from autocomplete), take the last 2
    if (cleaned.length > 4) {
      cleaned = cleaned.substring(0, 2) + cleaned.substring(cleaned.length - 2);
    }

    if (cleaned.length >= 2) {
      return `${cleaned.substring(0, 2)} / ${cleaned.substring(2, 4)}`;
    }
    return cleaned;
  };

  const getCardReference = (value) => {
    if (!value) {
      return "";
    }

    if (typeof value === "string") {
      return value;
    }

    if (typeof value !== "object") {
      return "";
    }

    return (
      value.reference ||
      value.source?.reference ||
      value.card?.source?.reference ||
      value.card?.brand?.reference ||
      value.brand?.reference ||
      value.name ||
      ""
    );
  };

  const normalizeCardType = (value) => {
    const reference = getCardReference(value);
    if (!reference) {
      return "";
    }

    return String(reference).toLowerCase().replace(/\s+/g, "_");
  };

  const detectedCardType = normalizeCardType(
    detectedSource?.source ||
      detectedSource?.card?.source ||
      detectedSource?.brand ||
      detectedSource?.card?.brand ||
      detectedSource?.name,
  );

  const previewCardTypes = availableCardTypes
    .map((type) => normalizeCardType(type))
    .filter(Boolean);

  const cardIcons = detectedCardType
    ? [detectedCardType]
    : previewCardTypes.slice(0, 4);

  return (
    <div className="mobbex-transparent-form">
      {description && <p className="mobbex-description">{description}</p>}

      {showBanner && (
        <div className="mobbex-checkout-banner">
          <img
            src="https://res.mobbex.com/images/sources/png/banner.png"
            alt={i18n.payment_methods || "Medios de pago"}
          />
        </div>
      )}

      <div id="mobbex-transparent-checkout-form">
        {errors.general && (
          <div className="alert alert-danger mobbex-error-general">
            {errors.general}
          </div>
        )}

        <div className="form-group mobbex-form-row">
          <label htmlFor="mobbex-card-number">
            {i18n.card_number || "Numero de tarjeta"} *
          </label>
          <div className="mobbex-card-number-input-wrapper">
            <input
              type="text"
              id="mobbex-card-number"
              className={`form-control ${errors.cardNumber ? "is-invalid" : ""}`}
              value={cardNumber}
              onChange={(e) => {
                setCardNumber(formatCardNumber(e.target.value));
                clearError("cardNumber");
              }}
              placeholder="1234 5678 9012 3456"
              maxLength="19"
              autoComplete="cc-number"
              inputMode="numeric"
            />
            {!!cardIcons.length && (
              <span className="mobbex-card-brands" aria-hidden="true">
                {cardIcons.map((cardType) => (
                  <img
                    key={cardType}
                    className={`mobbex-card-brand-logo ${detectedCardType ? "is-detected" : "is-preview"}`}
                    src={`https://res.mobbex.com/images/sources/original/${cardType}.png`}
                    alt={cardType}
                    loading="lazy"
                  />
                ))}
              </span>
            )}
          </div>
          {errors.cardNumber && (
            <div className="invalid-feedback">{errors.cardNumber}</div>
          )}
        </div>

        <div className="form-group mobbex-form-row">
          <label htmlFor="mobbex-card-name">
            {i18n.card_name || "Nombre del titular"} *
          </label>
          <input
            type="text"
            id="mobbex-card-name"
            className={`form-control ${errors.cardName ? "is-invalid" : ""}`}
            value={cardName}
            onChange={(e) => {
              setCardName(e.target.value);
              clearError("cardName");
            }}
            placeholder={
              i18n.card_name_placeholder || "Como figura en la tarjeta"
            }
            autoComplete="cc-name"
          />
          {errors.cardName && (
            <div className="invalid-feedback">{errors.cardName}</div>
          )}
        </div>

        <div className="form-group mobbex-form-row">
          <label htmlFor="mobbex-card-dni">
            {i18n.card_dni || "Numero de documento"} *
          </label>
          <input
            type="text"
            id="mobbex-card-dni"
            className={`form-control ${errors.cardDni ? "is-invalid" : ""}`}
            value={cardDni}
            onChange={(e) => {
              setCardDni(e.target.value.replace(/\D/g, ""));
              clearError("cardDni");
            }}
            placeholder="12345678"
            maxLength="15"
            inputMode="numeric"
          />
          {errors.cardDni && (
            <div className="invalid-feedback">{errors.cardDni}</div>
          )}
        </div>

        <div className="row">
          <div className="col-md-6">
            <div className="form-group mobbex-form-row">
              <label htmlFor="mobbex-card-expiration">
                {i18n.expiry || "Vencimiento"} *
              </label>
              <input
                type="text"
                id="mobbex-card-expiration"
                className={`form-control ${errors.cardExpiration ? "is-invalid" : ""}`}
                value={cardExpiration}
                onChange={(e) => {
                  setCardExpiration(formatExpiration(e.target.value));
                  clearError("cardExpiration");
                }}
                placeholder="MM / AA"
                maxLength="7"
                autoComplete="cc-exp"
                inputMode="numeric"
              />
              {errors.cardExpiration && (
                <div className="invalid-feedback">{errors.cardExpiration}</div>
              )}
            </div>
          </div>

          <div className="col-md-6">
            <div className="form-group mobbex-form-row">
              <label htmlFor="mobbex-security-code">
                {i18n.cvv || "Codigo de seguridad"} *
              </label>
              <input
                type="text"
                id="mobbex-security-code"
                className={`form-control ${errors.securityCode ? "is-invalid" : ""}`}
                value={securityCode}
                onChange={(e) => {
                  setSecurityCode(e.target.value.replace(/\D/g, ""));
                  clearError("securityCode");
                }}
                placeholder="123"
                maxLength="4"
                autoComplete="cc-csc"
                inputMode="numeric"
              />
              {errors.securityCode && (
                <div className="invalid-feedback">{errors.securityCode}</div>
              )}
            </div>
          </div>
        </div>

        <div className="form-group mobbex-form-row">
          <label htmlFor="mobbex-installments">
            {i18n.installments || "Cuotas"} *
          </label>
          <select
            id="mobbex-installments"
            className={`form-control ${errors.installments ? "is-invalid" : ""}`}
            value={selectedInstallment}
            onChange={(e) => {
              setSelectedInstallment(e.target.value);
              clearError("installments");
            }}
            disabled={isDetecting || installments.length === 0}
          >
            <option value="">
              {isDetecting
                ? i18n.loading_installments || "Cargando cuotas..."
                : installments.length === 0
                  ? i18n.enter_card || "Ingrese el numero de tarjeta"
                  : i18n.select_installments || "Seleccionar cuotas"}
            </option>
            {installments.map((installment) => (
              <option key={installment.reference} value={installment.reference}>
                {installment.name}
              </option>
            ))}
          </select>
          {errors.installments && (
            <div className="invalid-feedback">{errors.installments}</div>
          )}
        </div>

        {isProcessing && (
          <div className="alert alert-info mobbex-processing">
            {i18n.processing || "Procesando pago..."}
          </div>
        )}
      </div>
    </div>
  );
};

let mountedContainer = null;

const mountTransparentForm = () => {
  if (mountedContainer && mountedContainer.isConnected) {
    return;
  }

  const container = document.getElementById("mobbex-transparent-root");
  const config =
    window.mobbex_transparent_config ||
    (window.mbbx && window.mbbx.transparentConfig);

  if (container && config) {
    ReactDOM.render(<TransparentForm config={config} />, container);
    mountedContainer = container;
  }
};

document.addEventListener("DOMContentLoaded", mountTransparentForm);
document.addEventListener("change", (event) => {
  if (event.target && event.target.matches('input[name="payment-option"]')) {
    setTimeout(mountTransparentForm, 0);
  }
});
document.addEventListener("updatedCheckout", () => {
  setTimeout(() => {
    mountedContainer = null;
    mountTransparentForm();
  }, 0);
});

if (window.prestashop && typeof window.prestashop.on === "function") {
  window.prestashop.on("updatedCheckout", () => {
    mountedContainer = null;
    setTimeout(mountTransparentForm, 0);
  });
  window.prestashop.on("updatePaymentMethods", () => {
    mountedContainer = null;
    setTimeout(mountTransparentForm, 0);
  });
}

if (typeof window.MutationObserver !== "undefined") {
  const observer = new MutationObserver(() => {
    if (mountedContainer && !mountedContainer.isConnected) {
      mountedContainer = null;
    }
    mountTransparentForm();
  });
  observer.observe(document.body, { childList: true, subtree: true });
}

export default TransparentForm;
