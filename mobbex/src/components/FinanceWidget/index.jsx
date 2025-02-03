import { Button } from "./Button";
import { FinanceWidget } from "@mobbex/ecommerce-ui";
import { useState, useEffect } from "react";
import { createRoot } from "react-dom/client";

(function (window) {
  function Widget() {
    const [sources, setSources] = useState([]);
    const [ready, setReady] = useState(0);

    useEffect(() => {
      // Get sources and payment method selector
      fetch(mobbexWidget.sourcesUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          ids: mobbexWidget.product_ids,
          price: mobbexWidget.price,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          setSources(data.data);
          setReady(true);
        })
        .catch((error) => {
          console.log(error);
        });
    }, []);

    return mobbexWidget.type === "embed" ? (
      <FinanceWidget
        sources={sources}
        theme={mobbexWidget.theme}
        ready={ready}
      />
    ) : (
      <Button
        disable={!ready}
        sources={sources}
        text={mobbexWidget.text}
        logo={mobbexWidget.logo}
        theme={mobbexWidget.theme}
      />
    );
  }

  async function renderWidget() {
    // Wait for the container to be available
    let counter = 0;
    do {
      console.log("Waiting for container", counter);
      await new Promise((resolve) => setTimeout(resolve, 100));
      counter++;
    } while (!document.querySelector("#mbbxFinanceWidget") && counter < 50);

    // Create the root or return if container doesn't exist
    const container = document.querySelector("#mbbxFinanceWidget");
    if (!container) return console.error("Mobbex widget container not found");
    const root = createRoot(container);

    // Render the widget
    root.render(<Widget />);
  }

  document.addEventListener("DOMContentLoaded", renderWidget);
})(window);
