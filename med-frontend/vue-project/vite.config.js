import { fileURLToPath, URL } from "node:url";

import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import vueDevTools from "vite-plugin-vue-devtools";
import tailwindcss from "@tailwindcss/vite";

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  /*
    loadEnv legge i file .env: process.env da solo NON contiene le VITE_*
    dentro vite.config.js (venivano ignorate e il proxy usava sempre il default).
  */
  const env = loadEnv(mode, process.cwd(), "");
  const proxyTarget = env.VITE_PROXY_TARGET || "http://localhost:8000";

  return {
    plugins: [
      vue(),
      // I devtools servono solo in sviluppo: fuori dalla build di produzione
      ...(mode === "production" ? [] : [vueDevTools()]),
      // Tailwind compilato in build: niente piu' CDN, solo le classi realmente usate
      tailwindcss(),
    ],

    resolve: {
      alias: {
        "@": fileURLToPath(new URL("./src", import.meta.url)),
      },
    },

    server: {
      port: 5173,
      /*
        Proxy verso Laravel: in sviluppo le chiamate partono da /api (stessa origine)
        quindi non c'e' CORS ne' preflight da configurare.
        In produzione si imposta VITE_API_URL con il dominio reale dell'API.
      */
      proxy: {
        "/api": { target: proxyTarget, changeOrigin: true },
        "/storage": { target: proxyTarget, changeOrigin: true },
      },
    },
  };
});
