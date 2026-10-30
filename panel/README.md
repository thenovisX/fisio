# Panel del centro (código fuente)

React + TypeScript + Vite + Tailwind + shadcn/ui. Se compila a `app/www` (app Capacitor) y se copia a `web/panel`.

```bash
cd panel
pnpm i
npx vite build          # genera ../app/www
bash ../scripts/build-panel.sh   # copia app/www a web/panel
```

- `src/screens/`: Hoy, Agenda, Bandeja, Pacientes (fichas, notas, dolor EVA, bonos) y Más (tratamientos y ajustes).
- `src/components/nueva-cita.tsx`: apuntar una cita en una sola hoja (paciente, tratamiento, día y hueco).
- `public/avanzado/`: el panel clásico, para los ajustes que no están en el nuevo (usuarios, correo, textos legales…).

No se compila a un único HTML con scripts en línea porque la política CSP de la web lo prohíbe.
