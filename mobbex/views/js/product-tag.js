// Define displayProductTag globally or attach to a global object
// This allows it to be called multiple times for dynamic content
function displayProductTag() {
  anchorProp = {
    price: ".product-price-and-shipping",
    img: ".thumbnail-container",
    list: ".product-miniature",
    container: ".products",
  };

  const container = document.querySelector(anchorProp.container);
  if (!container) {
    console.warn(
      "Mobbex Warning: container not found in product-tags.js",
      container
    );
    return;
  }

  const products = container.querySelectorAll(anchorProp.list);
  if (products.length === 0) {
    console.warn(
      "Mobbex Warning: no products found with selector " +
        anchorProp.list +
        " in product-tags.js",
      products
    );
    return;
  }

  products.forEach((product) => {
    const tag_plan = product.querySelector(".mobbex-finance-data");
    if (!tag_plan) return;

    let plan = {
      id: tag_plan.dataset.productId,
      count: tag_plan.dataset.planCount,
      amount: tag_plan.dataset.planAmount,
      source: tag_plan.dataset.planSource,
      percentage: tag_plan.dataset.planPercentage,
    };

    if (mbbx.show_tag) addSourceFlag(product, anchorProp.img, plan);

    if (mbbx.show_banner) addFinanceBanner(product, anchorProp.price, plan);
  });
}

// Handles add flag over product image
function addSourceFlag(product, eImg, plan) {
  if (product.querySelector(".mobbex-wrapper.mobbex-id-" + plan.id)) {
    console.log(
      "Mobbex: Flag already exists for product " +
        plan.id +
        ". Skipping creation."
    );
    return;
  }

  const imgElement = product.querySelector(eImg);
  if (!imgElement) {
    console.error("no se encontró " + eImg + " a la que añadir el elemento");
    return;
  }

  // Wrapper. Flag parent element
  const wrapper = document.createElement("div");
  wrapper.classList.add("mobbex-wrapper", "mobbex-id-" + plan.id);

  // flag container
  const flagContainer = document.createElement("div");
  flagContainer.classList.add("mobbex-flag-container");

  // insert before shop product img (over with style)
  imgElement.parentNode?.insertBefore(wrapper, imgElement);
  wrapper.appendChild(imgElement);
  wrapper.appendChild(flagContainer);

  // Flag
  const flagBody = document.createElement("div");
  flagBody.classList.add("mobbex-flag");

  // add flag parts
  const flagTop = document.createElement("div");
  flagTop.classList.add("mobbex-flag-top");

  flagTop.innerHTML =
    "<span class='mobbex-flag-top-count' style='font-size:" +
    (plan.count < 9 ? "2" : "1.85") +
    "rem'>" +
    plan.count +
    "</span>" +
    "<span class='mobbex-flag-top-text'>" +
    financeText(plan.percentage).replace(" ", "<br>") +
    "</span>";

  const flagBottom = document.createElement("div");
  flagBottom.classList.add("mobbex-flag-bottom");

  flagBottom.innerHTML =
    "<span class='mobbex-flag-bottom-source'>Con " + plan.source + "</span>";

  // Build elements jerarchy
  flagBody.appendChild(flagTop);
  flagBody.appendChild(flagBottom);

  wrapper.appendChild(flagBody);
}

// Handles add banner
function addFinanceBanner(product, ePrice, plan) {
  if (product.querySelector(".mobbex-product-banner.mobbex-id-" + plan.id)) {
    console.log(
      "Mobbex: Banner already exists for product " +
        plan.id +
        ". Skipping creation."
    );
    return;
  }

  const priceElement = product.querySelector(ePrice);
  if (!priceElement) {
    console.error("No se encontró " + ePrice + " para añadir el elemento");
    return;
  }

  // create banner and its child elements
  const banner = document.createElement("div");

  banner.classList.add("mobbex-product-banner", "mobbex-id-" + plan.id);
  const bannerTop = document.createElement("div");
  bannerTop.classList.add("mobbex-product-banner-top");

  const bannerBottom = document.createElement("div");
  bannerBottom.classList.add("mobbex-product-banner-bottom");

  if (plan.count > 1)
    bannerTop.innerHTML =
      "<span class='mobbex-installment-span-left'>Hasta</span><span class='mobbex-installment-span-right'>" +
      plan.count +
      " Cuotas</span>";
  else
    bannerTop.innerHTML =
      "<span class='mobbex-installment-span-left'>En</span><span class='mobbex-installment-span-right'>" +
      plan.count +
      " Pago</span>";

  bannerBottom.innerHTML = financeText(plan.percentage) + " de $" + plan.amount;
  priceElement.parentNode?.insertBefore(banner, priceElement);

  // build banner elements jerarchy
  banner.appendChild(bannerTop);
  banner.appendChild(bannerBottom);
}

function financeText(percentage) {
  if (!percentage)
    console.error(
      "No se encontró el porcentaje de financiación. Ver installment.totals.financial"
    );
  if (percentage == 0) return "Sin interés";
  if (percentage < 0) return "Con descuento";
  if (percentage > 0) return "Con interés";
}

function initProductTags() {
  // Initial call
  displayProductTag();

  // Add Prestashop event listeners
  if (window.prestashop) {
    prestashop.on("updateProductList", displayProductTag);
  }

  // Use a MutationObserver to detect dynamic changes in the product list container
  const productListContainer = document.querySelector("section#products");

  if (productListContainer) {
    const observer = new MutationObserver(() => {
      // Re-run displayProductTag whenever the product list changes
      displayProductTag();
    });

    // Observe the body for changes in its children
    observer.observe(productListContainer, { childList: true, subtree: true });
  } else {
    console.error(
      "Mobbex Error: document.body not found for MutationObserver. This should not happen."
    );
  }
}

// Initial call when the DOM is fully loaded
document.addEventListener("DOMContentLoaded", initProductTags);
