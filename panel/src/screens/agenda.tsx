import { useMemo, useState } from "react";
import { toast } from "sonner";
import { ChevronLeft, ChevronRight, Lock, LockOpen, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { addDays, api, DIAS_C, fmtDay, iso, MESES, norm, parseISO, todayISO, type Cita } from "@/lib/api";
import { useApp, useLoad } from "@/lib/store";
import { Chip, Chips, Empty, Hoja, inputCls, Spinner } from "@/components/bits";
import { CitaRow, CitaSheet } from "@/components/cita";
import { DayTimeline, horasDelDia } from "@/components/timeline";
import { cn } from "@/lib/utils";

/* Agenda: el mes con puntos (cuántas citas tiene cada día) y, debajo, el día elegido con sus huecos. */

interface Mes { days: Record<string, { count: number; people: number; pending: number }>; closed_dates: string[] }

export function Agenda() {
  const { settings, can, reloadSettings } = useApp();
  const [day, setDay] = useState(() => { const d = localStorage.getItem("agendaDay"); localStorage.removeItem("agendaDay"); return d || todayISO(); });
  const [month, setMonth] = useState(day.slice(0, 7));
  const [open, setOpen] = useState<Cita | null>(null);
  const [buscar, setBuscar] = useState(false);
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
        <div className="mb-5 flex items-center justify-between">
          <h1 className="font-display text-[40px] leading-none">Agenda</h1>
          <Button variant="secondary" onClick={() => setBuscar(true)}><Search />Buscar cita</Button>
        </div>
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
      <BuscarCita open={buscar} onClose={() => setBuscar(false)} onPick={(c) => { setBuscar(false); setDay(c.date); setMonth(c.date.slice(0, 7)); setOpen(c); }} />
    </div>
  );
}

/* Buscar una cita por nombre, teléfono o tratamiento: de 2 meses atrás a 6 meses adelante. */
function BuscarCita({ open, onClose, onPick }: { open: boolean; onClose: () => void; onPick: (c: Cita) => void }) {
  const [q, setQ] = useState("");
  const [filtro, setFiltro] = useState<"proximas" | "pasadas" | "sin">("proximas");
  const hoy = todayISO();
  const lista = useLoad(() => open ? api<Cita[]>(`reservations&from=${addDays(hoy, -60)}&to=${addDays(hoy, 180)}`) : Promise.resolve(null), [open]);
  const t = norm(q.trim());
  const digits = q.replace(/\D/g, "");
  const res = (lista.data || [])
    .filter((c) => filtro === "proximas" ? c.date >= hoy && ["pendiente", "esperando", "confirmada"].includes(c.status)
      : filtro === "pasadas" ? c.date < hoy
      : c.date >= hoy && (c.status === "pendiente" || c.status === "esperando"))
    .filter((c) => !t || norm(c.name).includes(t) || norm(c.service).includes(t) || (digits.length >= 3 && c.phone.replace(/\D/g, "").includes(digits)));
  if (filtro === "pasadas") res.reverse();
  return (
    <Hoja open={open} onClose={onClose} title="Buscar cita">
      <div className="space-y-4">
        <input className={inputCls} autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nombre, teléfono o tratamiento" />
        <Chips>
          <Chip active={filtro === "proximas"} onClick={() => setFiltro("proximas")}>Próximas</Chip>
          <Chip active={filtro === "sin"} onClick={() => setFiltro("sin")}>Sin confirmar</Chip>
          <Chip active={filtro === "pasadas"} onClick={() => setFiltro("pasadas")}>Pasadas</Chip>
        </Chips>
        {lista.loading && !lista.data ? <div className="py-8 text-center"><Spinner /></div>
          : res.length ? <div className="space-y-1">{res.slice(0, 80).map((c) => <CitaRow key={c.id} c={c} onOpen={onPick} showDay />)}</div>
          : <Empty title="No hay citas" text={q ? "Prueba con otro nombre o con el teléfono." : "No hay citas con este filtro."} />}
      </div>
    </Hoja>
  );
}
