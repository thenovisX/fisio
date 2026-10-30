import * as SheetPrimitive from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import { cn } from "@/lib/utils";
import { STATUS_TXT, type Status } from "@/lib/api";

/* Piezas pequeñas que se repiten en todo el panel. */

/** Pantalla ancha (ordenador o tablet en horizontal). */
export function useWide() {
  const q = "(min-width: 900px)";
  const [wide, setWide] = useState(() => window.matchMedia(q).matches);
  useEffect(() => {
    const m = window.matchMedia(q);
    const f = () => setWide(m.matches);
    m.addEventListener("change", f);
    return () => m.removeEventListener("change", f);
  }, []);
  return wide;
}

/** Color estable para cada nombre: como las portadas de las listas de Spotify. */
const PALETAS = [
  ["#FF5B3A", "#7A1F12"], ["#9DBBA6", "#2C4A3A"], ["#F2B544", "#7A4B0E"], ["#6FA8DC", "#1F3A5C"],
  ["#E58FB4", "#6A2445"], ["#B9A3F0", "#3B2D6B"], ["#7FD1C1", "#1D5249"], ["#F08A5D", "#6B2A12"],
];
export function paleta(text: string) {
  let h = 0;
  for (const ch of text) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return PALETAS[h % PALETAS.length];
}
export function iniciales(name: string) {
  const p = name.trim().split(/\s+/).filter(Boolean);
  return ((p[0]?.[0] || "?") + (p.length > 1 ? p[p.length - 1][0] : "")).toUpperCase();
}

export function Avatar({ name, size = 44, className }: { name: string; size?: number; className?: string }) {
  const [a, b] = paleta(name || "?");
  return (
    <span
      aria-hidden="true"
      className={cn("inline-grid shrink-0 place-items-center rounded-full font-semibold text-black/80", className)}
      style={{ width: size, height: size, fontSize: size * 0.36, background: `linear-gradient(135deg, ${a}, ${b})` }}
    >
      {iniciales(name)}
    </span>
  );
}

const STATUS_CLS: Record<Status, string> = {
  pendiente: "bg-primary/15 text-primary",
  esperando: "bg-ambar/15 text-ambar",
  confirmada: "bg-salvia/15 text-salvia",
  rechazada: "bg-muted text-muted-foreground line-through",
  cancelada: "bg-muted text-muted-foreground line-through",
};
export function StatusPill({ status, className }: { status: Status; className?: string }) {
  return <span className={cn("inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold", STATUS_CLS[status], className)}>
    <span className="h-1.5 w-1.5 rounded-full bg-current" />{STATUS_TXT[status]}
  </span>;
}

/** Hoja que sube desde abajo en el móvil (como Wallapop) y sale por la derecha en el ordenador. */
export function Hoja({ open, onClose, title, subtitle, children, footer, wide: forceWide }: {
  open: boolean; onClose: () => void; title: ReactNode; subtitle?: ReactNode; children: ReactNode; footer?: ReactNode; wide?: boolean;
}) {
  const wide = useWide();
  return (
    <SheetPrimitive.Root open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetPrimitive.Portal>
        <SheetPrimitive.Overlay className="fixed inset-0 z-50 bg-black/70 backdrop-blur-[2px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
        <SheetPrimitive.Content
          aria-describedby={undefined}
          className={cn(
            "fixed z-50 flex flex-col bg-card shadow-2xl outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-200 data-[state=open]:duration-300",
            wide
              ? cn("inset-y-0 right-0 h-full w-full border-l data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right", forceWide ? "max-w-2xl" : "max-w-lg")
              : "inset-x-0 bottom-0 max-h-[94dvh] rounded-t-[28px] border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom",
          )}
        >
          {!wide && <div className="mx-auto mt-2.5 h-1.5 w-11 shrink-0 rounded-full bg-muted-foreground/30" />}
          <div className="flex items-start gap-3 px-5 pb-3 pt-4">
            <div className="min-w-0 flex-1">
              <SheetPrimitive.Title className="font-display text-[26px] leading-tight">{title}</SheetPrimitive.Title>
              {subtitle && <div className="mt-1 text-sm text-muted-foreground">{subtitle}</div>}
            </div>
            <SheetPrimitive.Close className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-secondary text-foreground/80 hover:text-foreground" aria-label="Cerrar">
              <X className="h-5 w-5" />
            </SheetPrimitive.Close>
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-5">{children}</div>
          {footer && <div className="border-t bg-card px-5 py-3 pb-safe">{footer}</div>}
        </SheetPrimitive.Content>
      </SheetPrimitive.Portal>
    </SheetPrimitive.Root>
  );
}

/** Fila de chips con scroll horizontal (días, tratamientos, filtros). */
export function Chips({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn("no-scrollbar -mx-5 flex gap-2 overflow-x-auto px-5 pb-1", className)}>{children}</div>;
}
export function Chip({ active, onClick, children, className, disabled }: { active?: boolean; onClick?: () => void; children: ReactNode; className?: string; disabled?: boolean }) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "shrink-0 rounded-full px-4 py-2 text-sm font-medium transition-colors disabled:opacity-35",
        active ? "bg-foreground text-background" : "bg-secondary text-foreground hover:bg-accent",
        className,
      )}
    >
      {children}
    </button>
  );
}

export function SectionTitle({ children, action }: { children: ReactNode; action?: ReactNode }) {
  return (
    <div className="mb-3 mt-8 flex items-end justify-between gap-3">
      <h2 className="font-display text-[22px] leading-none">{children}</h2>
      {action}
    </div>
  );
}

export function Empty({ icon, title, text, action }: { icon?: ReactNode; title: string; text?: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center rounded-3xl border border-dashed px-6 py-10 text-center">
      {icon && <div className="mb-3 text-muted-foreground">{icon}</div>}
      <p className="font-semibold">{title}</p>
      {text && <p className="mt-1 max-w-xs text-sm text-muted-foreground">{text}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function Field({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-[13px] font-semibold text-foreground/90">{label}{hint && <span className="ml-1.5 font-normal text-muted-foreground">{hint}</span>}</span>
      {children}
    </label>
  );
}

export const inputCls = "w-full rounded-2xl border border-input bg-background/60 px-4 py-3 text-[15px] text-foreground placeholder:text-muted-foreground/70 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary";

export function Spinner({ className }: { className?: string }) {
  return <span className={cn("inline-block h-5 w-5 animate-spin rounded-full border-2 border-current border-r-transparent", className)} aria-label="Cargando" />;
}
