import { useMemo, useState } from "react";
import { toast } from "sonner";
import { ChevronLeft, ChevronRight, Lock, LockOpen } from "lucide-react";
import { Button } from "@/components/ui/button";
import { addDays, api, DIAS_C, fmtDay, iso, MESES, parseISO, todayISO, type Cita } from "@/lib/api";
import { useApp, useLoad } from "@/lib/store";
import { Spinner } from "@/components/bits";
import { CitaSheet } from "@/components/cita";
import { DayTimeline, horasDelDia } from "@/components/timeline";
import { cn } from "@/lib/utils";

/* Agenda: el mes con puntos (cuántas citas tiene cada día) y, debajo, el día elegido con sus huecos. */

interface Mes { days: Record<string, { count: number; people: number; pending: number }>; closed_dates: string[] }

export function Agenda() {
  const { settings, can, reloadSettings } = useApp();
  const [day, setDay] = useState(() => { const d = localStorage.getItem("agendaDay"); localStorage.removeItem("agendaDay"); return d || todayISO(); });
  const [month, setMonth] = useState(day.slice(0, 7));
  const [open, setOpen] = useState<Cita | null>(null);
  const mes = useLoad(() => api<Mes>("month&month=" + month), [month]);
  const lista = useLoad(() => api<Cita[]>(`reservations&from=${day}&to=${day}`), [day]);

  const celdas = useMemo(() => {
    const first = parseISO(month + "-01");
    const offset = (first.getDay() + 6) % 7;
    const n = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    return [...Array(offset).fill(null), ...Array.from({ length: n }, (_, i) => `${month}-${String(i + 1).padStart(2, "0")}`)];
  }, [month]);
  const mover = (k: number) => { const d = parseISO(month + "-01"); d.setMonth(d.getMonth() + k); setMonth(iso(d).slice(0, 7)); };
  const cerrado = settings?.closed_dates?.includes(day);
  const max = Math.max(1, ...Object.values(mes.data?.days || {}).map((d) => d.count));

  async function toggleCerrado() {
    const list = new Set(settings?.closed_dates || []);
    if (list.has(day)) list.delete(day); else list.add(day);
    try {
      await api("settings_save", { body: { closed_dates: [...list] } });
      await reloadSettings();
      mes.reload();
      toast(cerrado ? "Día abierto otra vez" : "Día cerrado: la web no deja pedir cita");
    } catch (e: any) { toast.error(e.message); }
  }

  return (
    <div className="lg:grid lg:grid-cols-[360px_1fr] lg:gap-10">
      <div className="lg:sticky lg:top-6 lg:self-start">
        <h1 className="mb-5 font-display text-[40px] leading-none">Agenda</h1>
        <div className="rounded-[28px] bg-card p-4 ring-1 ring-border">
          <div className="mb-3 flex items-center justify-between">
            <Button variant="ghost" size="icon" onClick={() => mover(-1)} aria-label="Mes anterior"><ChevronLeft /></Button>
            <p className="font-semibold">{MESES[parseISO(month + "-01").getMonth()].replace(/^./, (x) => x.toUpperCase())} {month.slice(0, 4)}</p>
            <Button variant="ghost" size="icon" onClick={() => mover(1)} aria-label="Mes siguiente"><ChevronRight /></Button>
          </div>
          <div className="grid grid-cols-7 gap-1 text-center text-[11px] uppercase text-muted-foreground">
            {[1, 2, 3, 4, 5, 6, 0].map((d) => <span key={d}>{DIAS_C[d].slice(0, 2)}</span>)}
          </div>
          <div className="mt-1 grid grid-cols-7 gap-1">
            {celdas.map((d, i) => {
              if (!d) return <span key={"x" + i} />;
              const info = mes.data?.days[d];
              const sel = d === day;
              const closed = !horasDelDia(settings, d).length;
              return (
                <button key={d} type="button" onClick={() => setDay(d)} aria-pressed={sel}
                  className={cn("relative flex aspect-square flex-col items-center justify-center rounded-2xl text-sm tabular transition-colors",
                    sel ? "bg-foreground text-background" : "hover:bg-secondary", d === todayISO() && !sel && "ring-1 ring-primary", closed && !sel && "text-muted-foreground/40")}>
                  {Number(d.slice(8))}
                  {info && info.count > 0 && (
                    <span className={cn("absolute bottom-1.5 h-1 rounded-full", info.pending ? "bg-primary" : sel ? "bg-background" : "bg-salvia")} style={{ width: 6 + (info.count / max) * 14 }} />
                  )}
                </button>
              );
            })}
          </div>
        </div>
      </div>

      <div className="mt-8 lg:mt-[68px]">
        <div className="mb-4 flex flex-wrap items-center gap-2">
          <Button variant="secondary" size="icon" onClick={() => setDay(addDays(day, -1))} aria-label="Día anterior"><ChevronLeft /></Button>
          <Button variant="secondary" size="icon" onClick={() => setDay(addDays(day, 1))} aria-label="Día siguiente"><ChevronRight /></Button>
          <h2 className="ml-1 font-display text-2xl">{fmtDay(day)}</h2>
          {day !== todayISO() && <Button variant="ghost" size="sm" onClick={() => { setDay(todayISO()); setMonth(todayISO().slice(0, 7)); }}>Hoy</Button>}
          {can("reservas") && (
            <Button variant="outline" size="sm" className="ml-auto" onClick={toggleCerrado}>
              {cerrado ? <><LockOpen />Abrir este día</> : <><Lock />Cerrar este día</>}
            </Button>
          )}
        </div>
        {lista.loading && !lista.data ? <div className="py-10 text-center"><Spinner /></div> : <DayTimeline date={day} citas={lista.data || []} onOpen={setOpen} />}
      </div>
      <CitaSheet cita={open} onClose={() => setOpen(null)} />
    </div>
  );
}
