import { useState } from "react";
import { Clock, Inbox, MessageCircle } from "lucide-react";
import { api, relDay, type Cita } from "@/lib/api";
import { useLoad } from "@/lib/store";
import { Avatar, Empty, SectionTitle, Spinner, StatusPill } from "@/components/bits";
import { CitaSheet } from "@/components/cita";

/* Bandeja: las peticiones que llegan de la web, como los chats de Wallapop. Una toca, se acepta o se rechaza. */

function hace(ts: string) {
  const min = Math.round((Date.now() - new Date(ts.replace(" ", "T")).getTime()) / 60000);
  if (min < 1) return "ahora";
  if (min < 60) return `hace ${min} min`;
  const h = Math.round(min / 60);
  if (h < 24) return `hace ${h} h`;
  return `hace ${Math.round(h / 24)} d`;
}

function Mensaje({ c, onOpen }: { c: Cita; onOpen: (c: Cita) => void }) {
  return (
    <button type="button" onClick={() => onOpen(c)} className="flex w-full items-start gap-3 rounded-3xl p-3 text-left transition-colors hover:bg-secondary/70">
      <div className="relative">
        <Avatar name={c.name} size={52} />
        {c.status === "pendiente" && <span className="absolute -right-0.5 -top-0.5 h-3.5 w-3.5 rounded-full border-2 border-background bg-primary" />}
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-baseline justify-between gap-2">
          <p className="truncate font-semibold">{c.name}</p>
          <span className="shrink-0 text-xs text-muted-foreground">{hace(c.created_at)}</span>
        </div>
        <p className="mt-0.5 flex items-center gap-1.5 text-[14px] text-foreground/85"><Clock className="h-3.5 w-3.5 text-primary" />{relDay(c.date)} · {c.time} · {c.service || "Cita"}</p>
        {c.notes && <p className="mt-1 line-clamp-2 text-[13px] text-muted-foreground">“{c.notes}”</p>}
        <div className="mt-2"><StatusPill status={c.status} /></div>
      </div>
    </button>
  );
}

export function Bandeja() {
  const { data, loading } = useLoad(() => api<{ nuevas: Cita[]; esperando: Cita[] }>("requests"), []);
  const [open, setOpen] = useState<Cita | null>(null);
  const nuevas = data?.nuevas || [];
  const esperando = data?.esperando || [];

  return (
    <div className="mx-auto max-w-2xl">
      <h1 className="font-display text-[40px] leading-none">Bandeja</h1>
      <p className="mt-2 text-muted-foreground">Las citas que piden desde la web. Acéptalas y el paciente recibe un WhatsApp para confirmar.</p>
      {loading && !data ? <div className="py-12 text-center"><Spinner /></div> : (
        <>
          <SectionTitle>Nuevas {nuevas.length > 0 && <span className="ml-1 rounded-full bg-primary px-2 py-0.5 align-middle font-sans text-sm text-primary-foreground">{nuevas.length}</span>}</SectionTitle>
          {nuevas.length ? <div className="space-y-1">{nuevas.map((c) => <Mensaje key={c.id} c={c} onOpen={setOpen} />)}</div>
            : <Empty icon={<Inbox className="h-8 w-8" />} title="Todo al día" text="Cuando alguien pida cita en la web, aparecerá aquí y te avisaremos." />}
          <SectionTitle>Esperando al paciente</SectionTitle>
          {esperando.length ? <div className="space-y-1">{esperando.map((c) => <Mensaje key={c.id} c={c} onOpen={setOpen} />)}</div>
            : <Empty icon={<MessageCircle className="h-8 w-8" />} title="Nadie pendiente de confirmar" />}
        </>
      )}
      <CitaSheet cita={open} onClose={() => setOpen(null)} />
    </div>
  );
}
