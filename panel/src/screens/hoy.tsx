import { useEffect, useState } from "react";
import { ArrowRight, Inbox, Phone, Sparkles } from "lucide-react";
import { ACTIVE, addDays, api, endOf, fmtDay, nowMin, relDay, telHref, toMin, todayISO, type Cita } from "@/lib/api";
import { useApp, useLoad } from "@/lib/store";
import { Avatar, Chips, SectionTitle, Spinner } from "@/components/bits";
import { CitaSheet } from "@/components/cita";
import { DayTimeline } from "@/components/timeline";
import { cn } from "@/lib/utils";

/* Inicio: lo que pasa ahora (como «Sonando ahora» en Spotify), la agenda de hoy con sus huecos y los próximos días. */

function saludo() {
  const h = new Date().getHours();
  return h < 13 ? "Buenos días" : h < 21 ? "Buenas tardes" : "Buenas noches";
}

export function Hoy() {
  const { me, settings, pending, waiting, setTab } = useApp();
  const hoy = todayISO();
  const fin = addDays(hoy, 6);
  const { data, loading } = useLoad(() => api<Cita[]>(`reservations&from=${hoy}&to=${fin}`), [hoy]);
  const [open, setOpen] = useState<Cita | null>(null);
  const [, tick] = useState(0);
  useEffect(() => { const t = setInterval(() => tick((x) => x + 1), 30000); return () => clearInterval(t); }, []);

  const deHoy = (data || []).filter((c) => c.date === hoy);
  const activasHoy = deHoy.filter((c) => ACTIVE.includes(c.status));
  const m = nowMin();
  const ahora = activasHoy.find((c) => toMin(c.time) <= m && m < toMin(c.time) + c.duration);
  const siguiente = activasHoy.find((c) => toMin(c.time) > m);
  const proximos = Array.from({ length: 6 }, (_, i) => addDays(hoy, i + 1)).map((d) => ({ d, list: (data || []).filter((c) => c.date === d && ACTIVE.includes(c.status)) }));
  const nombre = (me?.name || me?.username || "").split(" ")[0];

  return (
    <div>
      <header className="mb-6">
        <p className="text-sm text-muted-foreground">{fmtDay(hoy)}</p>
        <h1 className="font-display text-[40px] leading-[1.02] sm:text-5xl">{saludo()}{nombre ? <>, <em className="font-light text-primary">{nombre}</em></> : null}</h1>
        {settings && <p className="mt-1 text-muted-foreground">{settings.name}</p>}
      </header>

      {/* Ahora / siguiente */}
      <div className="grid gap-3 lg:grid-cols-[1.4fr_1fr]">
        {ahora ? <EnCurso c={ahora} onOpen={setOpen} /> : siguiente ? <Siguiente c={siguiente} onOpen={setOpen} /> : (
          <div className="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-salvia/30 via-secondary to-card p-6">
            <Sparkles className="h-6 w-6 text-salvia" />
            <p className="mt-6 font-display text-3xl">{loading ? "Cargando…" : activasHoy.length ? "Ya no quedan citas hoy" : "Hoy no hay citas"}</p>
            <p className="mt-1 text-muted-foreground">Toca un hueco libre de la agenda para apuntar a alguien.</p>
          </div>
        )}
        <div className="grid grid-cols-3 gap-3 lg:grid-cols-1">
          <Stat n={activasHoy.length} label="citas hoy" />
          <Stat n={pending} label={pending === 1 ? "petición nueva" : "peticiones nuevas"} hot={pending > 0} onClick={() => setTab("bandeja")} />
          <Stat n={waiting} label="esperan confirmar" onClick={() => setTab("bandeja")} />
        </div>
      </div>
      {ahora && siguiente && <button type="button" onClick={() => setOpen(siguiente)} className="mt-3 flex w-full items-center gap-3 rounded-2xl bg-secondary/60 px-4 py-3 text-left text-sm hover:bg-secondary">
        <span className="text-muted-foreground">Después</span><span className="font-semibold tabular">{siguiente.time}</span><span className="truncate">{siguiente.name} · {siguiente.service}</span><ArrowRight className="ml-auto h-4 w-4 shrink-0" />
      </button>}

      <SectionTitle>Agenda de hoy</SectionTitle>
      {loading && !data ? <div className="py-10 text-center"><Spinner /></div> : <DayTimeline date={hoy} citas={deHoy} onOpen={setOpen} />}

      <SectionTitle action={<button className="text-sm font-semibold text-muted-foreground hover:text-foreground" onClick={() => setTab("agenda")}>Ver agenda</button>}>Próximos días</SectionTitle>
      <Chips className="gap-3">
        {proximos.map(({ d, list }) => (
          <button key={d} type="button" onClick={() => { localStorage.setItem("agendaDay", d); setTab("agenda"); }}
            className="w-[168px] shrink-0 rounded-3xl bg-card p-4 text-left ring-1 ring-border transition-colors hover:bg-secondary">
            <p className="text-sm font-semibold">{relDay(d)}</p>
            <p className="mt-3 font-display text-4xl tabular">{list.length}</p>
            <p className="text-xs text-muted-foreground">{list.length === 1 ? "cita" : "citas"}</p>
            <div className="mt-3 flex -space-x-2">
              {list.slice(0, 5).map((c) => <Avatar key={c.id} name={c.name} size={26} className="ring-2 ring-card" />)}
            </div>
          </button>
        ))}
      </Chips>

      <CitaSheet cita={open} onClose={() => setOpen(null)} />
    </div>
  );
}

function Stat({ n, label, hot, onClick }: { n: number; label: string; hot?: boolean; onClick?: () => void }) {
  return (
    <button type="button" onClick={onClick} disabled={!onClick}
      className={cn("flex flex-col justify-between rounded-3xl p-4 text-left ring-1 ring-border transition-colors lg:flex-row lg:items-center", hot ? "bg-primary text-primary-foreground ring-primary" : "bg-card hover:bg-secondary")}>
      <span className="font-display text-4xl leading-none tabular">{n}</span>
      <span className={cn("mt-2 text-[13px] leading-tight lg:mt-0", hot ? "" : "text-muted-foreground")}>{label}</span>
    </button>
  );
}

function EnCurso({ c, onOpen }: { c: Cita; onOpen: (c: Cita) => void }) {
  const m = nowMin();
  const pct = Math.min(100, Math.max(0, ((m - toMin(c.time)) / c.duration) * 100));
  const quedan = toMin(c.time) + c.duration - m;
  return (
    <button type="button" onClick={() => onOpen(c)} className="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-primary/40 via-primary/10 to-card p-6 text-left ring-1 ring-primary/30">
      <p className="flex items-center gap-2 text-xs font-bold uppercase tracking-[.16em] text-primary"><span className="relative flex h-2 w-2"><span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75" /><span className="relative inline-flex h-2 w-2 rounded-full bg-primary" /></span>En sesión ahora</p>
      <div className="mt-5 flex items-center gap-4">
        <Avatar name={c.name} size={64} />
        <div className="min-w-0">
          <p className="truncate font-display text-3xl leading-tight">{c.name}</p>
          <p className="truncate text-foreground/70">{c.service || "Cita"}</p>
        </div>
      </div>
      <div className="mt-6 h-1.5 overflow-hidden rounded-full bg-foreground/15"><div className="h-full rounded-full bg-foreground" style={{ width: pct + "%" }} /></div>
      <div className="mt-2 flex justify-between text-xs text-foreground/70 tabular"><span>{c.time}</span><span>quedan {quedan} min</span><span>{endOf(c)}</span></div>
    </button>
  );
}

function Siguiente({ c, onOpen }: { c: Cita; onOpen: (c: Cita) => void }) {
  const falta = toMin(c.time) - nowMin();
  return (
    <div className="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-secondary to-card p-6 ring-1 ring-border">
      <p className="text-xs font-bold uppercase tracking-[.16em] text-muted-foreground">Siguiente · {falta < 60 ? `en ${falta} min` : `a las ${c.time}`}</p>
      <button type="button" onClick={() => onOpen(c)} className="mt-5 flex w-full items-center gap-4 text-left">
        <Avatar name={c.name} size={64} />
        <div className="min-w-0">
          <p className="truncate font-display text-3xl leading-tight">{c.name}</p>
          <p className="truncate text-foreground/70">{c.service || "Cita"} · {c.time}–{endOf(c)}</p>
        </div>
      </button>
      <div className="mt-5 flex gap-2">
        <a href={telHref(c.phone)} className="inline-flex h-10 items-center gap-2 rounded-full bg-foreground px-4 text-sm font-semibold text-background"><Phone className="h-4 w-4" />Llamar</a>
        {c.status !== "confirmada" && <span className="inline-flex h-10 items-center gap-2 rounded-full bg-ambar/15 px-4 text-sm font-semibold text-ambar"><Inbox className="h-4 w-4" />Sin confirmar</span>}
      </div>
    </div>
  );
}
