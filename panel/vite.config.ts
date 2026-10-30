import path from "path";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";

// El panel se compila a app/www (la app Android con Capacitor) y scripts/build-panel.sh lo copia a web/panel.
// Rutas relativas (base './') para que funcione en /panel/ y dentro de la app; sin scripts en línea por la CSP.
export default defineConfig({
  base: "./",
  plugins: [react()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  build: {
    outDir: "../app/www",
    emptyOutDir: true,
    assetsDir: "assets",
    modulePreload: { polyfill: false },
  },
});
