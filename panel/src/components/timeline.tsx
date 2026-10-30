import { Plus } from "lucide-react";
import { ACTIVE, fromMin, nowMin, parseISO, toMin, todayISO, type Ajustes, type Cita } from "@/lib/api";
import { useApp } from "@/lib/store";
import { CitaRow } from "./cita";

/* Día de agenda: las citas en orden y, entre medias, los huecos libres del horario.
 * Tocar un hueco abre «Nueva cita» con ese día y esa hora: llenar la agenda es un toque. */

export function horasDelDia(settings: Ajustes | null, date: string): [string, string][] {
  if (!settings || settings.closed_dates?.includes(date)) return [];
  const wd = String(((parseISO(date).getDay() + 6) % 7) + 1);
  return settings.hours?.[wd] || [];
}

type Item = { kind: "cita"; c: Cita } | { kind: "hueco"; from: number; to: number };

export function DayTimeline({ date, citas, onOpen }: { date: string; citas: Cita[]; onOpen: (c: Cita) => void }) {
  const { settings, nuevaCita, can } = useApp();
  const ranges = horasDelDia(settings, date);
  const cap = Math.max(1, Number(settings?.capacity) || 1);
  const minHueco = Math.max(15, Math.min(...[30, Number(settings?.table_minutes) || 45]));
  const activas = citas.filter((c) => ACTIVE.includes(c.status));
  const esHoy = date === todayISO();
  const ahora = nowMin();

  // Huecos: tramos del horario en los que hay menos citas que fisioterapeutas
  const items: Item[] = [];
  const ocupado = (m: number) => activas.filter((c) => toMin(c.time) <= m && m < toMin(c.time) + (c.duration || 45)).length;
  const huecos: { from: number; to: number }[] = [];
  for (const [a, b] of ranges) {
    let start = -1;
    for (let m = toMin(a); m <= toMin(b); m += 5) {
      const libre = m < toMin(b) && ocupado(m) < cap && (!esHoy || m >= ahora);
      if (libre && start < 0) start = m;
      if (!libre && start >= 0) { if (m - start >= minHueco) huecos.push({ from: start, to: m }); start = -1; }
    }
  }
  citas.forEach((c) => items.push({ kind: "cita", c }));
  huecos.forEach((h) => items.push({ kind: "hueco", ...h }));
  items.sort((x, y) => (x.kind === "cita" ? toMin(x.c.time) : x.from) - (y.kind === "cita" ? toMin(y.c.time) : y.from) || (x.kind === "cita" ? -1 : 1));

  if (!ranges.length && !citas.length) {
    return <p className="rounded-3xl border border-dashed px-5 py-8 text-center text-muted-foreground">Cerrado este día.</p>;
  }
  return (
    <div className="space-y-1">
      {items.map((it) => it.kind === "cita" ? (
        <CitaRow key={"c" + it.c.id} c={it.c} onOpen={onOpen}
          now={esHoy && ACTIVE.includes(it.c.status) && toMin(it.c.time) <= ahora && ahora < toMin(it.c.time) + it.c.duration} />
      ) : (
        <button key={"h" + it.from} type="button" disabled={!can("reservas")}
          onClick={() => nuevaCita({ date, time: fromMin(it.from) })}
          className="group flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left text-muted-foreground transition-colors hover:bg-secondary/50 hover:text-foreground">
          <span className="w-14 shrink-0 text-right text-[13px] tabular">{fromMin(it.from)}</span>
          <span className="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-full border border-dashed border-muted-foreground/40 group-hover:border-primary group-hover:text-primary"><Plus className="h-4 w-4" /></span>
          <span className="text-[14px]">Hueco libre hasta las {fromMin(it.to)} <span className="opacity-60">· {it.to - it.from} min</span></span>
        </button>
      ))}
    </div>
  );
}
