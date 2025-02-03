import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import autoprefixer from "autoprefixer";
import cssInjectedByJsPlugin from "vite-plugin-css-injected-by-js";

export default defineConfig({
  plugins: [react(), cssInjectedByJsPlugin()],
  css: {
    postcss: {
      plugins: [autoprefixer],
    },
  },
  build: {
    outDir: "mobbex/views/static",
    minify: false,
    rollupOptions: {
      input: {
        FinanceWidget: "mobbex/src/components/FinanceWidget/index.jsx",
      },
      output: {
        entryFileNames: "[name].js",
        format: "es",
        assetFileNames: "[name].[ext]",
      },
    },
  },
});
