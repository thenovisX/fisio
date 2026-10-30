import { useEffect, useState } from "react";
import { toast } from "sonner";
import { Check, ChevronRight, Clock, MessageCircle, Pencil, Phone, Send, Trash2, UserPlus, UserRound, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { api, endOf, fmtDay, relDay, telHref, waHref, type Cita } from "@/lib/api";
import { useApp } from "@/lib/store";
import { cn } from "@/lib/utils";
import { Avatar, Chip, Hoja, StatusPill, Spinner } from "./bits";

/* Una cita en una lista (fila tipo canción de Spotify) y su hoja de acciones. */

export function CitaRow({ c, onOpen, showDay, now }: { c: Cita; onOpen: (c: Cita) => void; showDay?: boolean; now?: boolean }) {
  return (
    <button
      type="button"
      onClick={() => onOpen(c)}
      className={cn(
        "group flex w-full items-center gap-3 rounded-2xl px-2 py-2.5 text-left transition-colors hover:bg-secondary/70 active:bg-secondary",
        now && "bg-primary/10 ring-1 ring-primary/40",
        (c.status === "cancelada" || c.status === "rechazada") && "opacity-50",
      )}
    >
      <div className="w-14 shrink-0 text-right tabular">
        <p className="text-[15px] font-semibold leading-none">{c.time}</p>
        <p className="mt-1 text-[11px] text-muted-foreground">{showDay ? relDay(c.date) : endOf(c)}</p>
      </div>
      <Avatar name={c.name} size={42} />
      <div className="min-w-0 flex-1">
        <p className="truncate text-[15px] font-semibold">{c.name}</p>
        <p className="truncate text-[13px] text-muted-foreground">{c.service || "Cita"} · {c.duration} min{c.notes ? " · " + c.notes : ""}</p>
      </div>
      {c.status !== "confirmada" && <StatusPill status={c.status} className="hidden sm:inline-flex" />}
      {c.status === "pendiente" && <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-primary sm:hidden" />}
      {c.status === "esperando" && <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-ambar sm:hidden" />}
      <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground/60 group-hover:text-foreground" />
    </button>
  );
}

export function CitaSheet({ cita, onClose }: { cita: Cita | null; onClose: () => void }) {
  const { bump, can, openPatient, nuevaCita, settings } = useApp();
  const [busy, setBusy] = useState("");
  const [rechazo, setRechazo] = useState<null | { slots: string[]; text_slots: string; text_none: string; wa_number: string }>(null);
  const [pick, setPick] = useState<string[]>([]);
  useEffect(() => { setRechazo(null); setPick([]); }, [cita?.id]);
  if (!cita) return <Hoja open={false} onClose={onClose} title="">{null}</Hoja>;
  const c = cita;
  const puede = can("reservas");

  async function run(key: string, fn: () => Promise<void>) {
    setBusy(key);
    try { await fn(); } catch (e: any) { toast.error(e.message); } finally { setBusy(""); }
  }
  const setStatus = (status: string, msg: string) => run(status, async () => {
    const r = await api<Cita & { wa_sent?: boolean; wa_error?: string }>("reservation_status", { body: { id: c.id, status } });
    if (status === "esperando") {
      if (r.wa_sent) toast.success("WhatsApp enviado", { description: "Cuando el paciente pulse el enlace, la cita pasa a confirmada." });
      else if (r.wa_url) { window.open(r.wa_url, "_blank"); toast.success("Aceptada", { description: r.wa_error ? "El envío automático falló: se abre WhatsApp para mandarlo tú." : "Envía el WhatsApp que se ha abierto." }); }
      else toast.success(msg);
    } else toast.success(msg);
    bump();
    onClose();
  });
  const startReject = () => run("rechazo", async () => {
    const info = await api("reject_info&id=" + c.id);
    setRechazo(info);
  });
  const sendReject = () => run("rechazada", async () => {
    if (rechazo?.wa_number) {
      const text = pick.length ? rechazo.text_slots.replace("{horas}", pick.join(", ")) : rechazo.text_none;
      window.open("https://wa.me/" + rechazo.wa_number + "?text=" + encodeURIComponent(text), "_blank");
    }
    await api("reservation_status", { body: { id: c.id, status: "rechazada" } });
    toast("Petición rechazada");
    bump();
    onClose();
  });
  const borrar = () => run("borrar", async () => {
    if (!confirm("¿Borrar esta cita? Desaparece de la agenda.")) throw new Error("Cancelado");
    await api("reservation_delete", { body: { id: c.id } });
    toast("Cita borrada");
    bump();
    onClose();
  });
  const crearFicha = () => run("ficha", async () => {
    const p = await api<{ id: number }>("patient_save", { body: { name: c.name, phone: c.phone, email: c.email, reason: c.notes, reservation_id: c.id } });
    toast.success("Ficha creada");
    bump();
    onClose();
    openPatient(p.id);
  });

  return (
    <Hoja
      open={!!cita}
      onClose={onClose}
      title={c.name}
      subtitle={<span className="flex flex-wrap items-center gap-2"><StatusPill status={c.status} /> {c.source === "web" ? "Pedida en la web" : "Apuntada en el panel"}</span>}
      footer={puede && (
        <div className="flex gap-2">
          {c.status === "pendiente" && !rechazo && <>
            <Button variant="secondary" className="flex-1" onClick={startReject} disabled={!!busy}>{busy === "rechazo" ? <Spinner /> : <X />}Rechazar</Button>
            <Button className="flex-[2]" onClick={() => setStatus("esperando", "Aceptada")} disabled={!!busy}>{busy === "esperando" ? <Spinner /> : <Send />}Aceptar y enviar WhatsApp</Button>
          </>}
          {c.status === "pendiente" && rechazo && <>
            <Button variant="secondary" className="flex-1" onClick={() => setRechazo(null)}>Volver</Button>
            <Button variant="destructive" className="flex-[2]" onClick={sendReject} disabled={!!busy}>{rechazo.wa_number ? "Rechazar y avisar por WhatsApp" : "Rechazar"}</Button>
          </>}
          {c.status === "esperando" && <>
            {c.wa_url && <Button variant="secondary" className="flex-1" onClick={() => c.wa_auto ? run("wa", async () => { const r = await api("reservation_whatsapp", { body: { id: c.id } }); if (r.wa_sent) toast.success("WhatsApp reenviado"); else { window.open(c.wa_url, "_blank"); } }) : window.open(c.wa_url, "_blank")}><Send />Reenviar</Button>}
            <Button className="flex-[2]" onClick={() => setStatus("confirmada", "Confirmada")} disabled={!!busy}><Check />Marcar confirmada</Button>
          </>}
          {c.status === "confirmada" && <>
            <Button variant="secondary" className="flex-1" onClick={() => setStatus("cancelada", "Cita cancelada")} disabled={!!busy}>Cancelar cita</Button>
            <Button className="flex-1" onClick={() => { onClose(); nuevaCita({ edit: c }); }}><Pencil />Cambiar</Button>
          </>}
          {(c.status === "cancelada" || c.status === "rechazada") && (
            <Button className="flex-1" onClick={() => { onClose(); nuevaCita({ name: c.name, phone: c.phone, service_id: c.service_id, patient_id: c.patient_id }); }}>Darle otra cita</Button>
          )}
        </div>
      )}
    >
      {rechazo ? (
        <div className="space-y-4">
          <p className="text-sm text-muted-foreground">
            {rechazo.slots.length ? "Toca las horas que le puedes ofrecer ese día. Se abrirá WhatsApp con el mensaje escrito." : "Ese día no queda ningún hueco cerca. Le diremos que pida otro día."}
          </p>
          {rechazo.slots.length > 0 && (
            <div className="flex flex-wrap gap-2">
              {rechazo.slots.map((s) => <Chip key={s} active={pick.includes(s)} onClick={() => setPick((p) => p.includes(s) ? p.filter((x) => x !== s) : [...p, s].sort())}>{s}</Chip>)}
            </div>
          )}
          <p className="rounded-2xl bg-background/60 p-4 text-sm leading-relaxed text-foreground/85">
            {pick.length ? rechazo.text_slots.replace("{horas}", pick.join(", ")) : rechazo.text_none}
          </p>
        </div>
      ) : (
        <div className="space-y-5">
          <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary/25 via-secondary to-secondary p-5">
            <p className="text-sm text-foreground/70">{fmtDay(c.date)}</p>
            <p className="mt-1 font-display text-5xl tabular">{c.time}<span className="ml-2 text-2xl text-foreground/50">– {endOf(c)}</span></p>
            <p className="mt-3 flex items-center gap-2 text-[15px] font-semibold"><Clock className="h-4 w-4 text-primary" />{c.service || "Cita sin tratamiento"} · {c.duration} min</p>
          </div>
          <div className="grid grid-cols-3 gap-2">
            <a href={telHref(c.phone)} className="flex flex-col items-center gap-1.5 rounded-2xl bg-secondary py-3 text-[13px] font-medium hover:bg-accent"><Phone className="h-5 w-5" />Llamar</a>
            <a href={waHref(c.phone)} target="_blank" rel="noopener" className="flex flex-col items-center gap-1.5 rounded-2xl bg-secondary py-3 text-[13px] font-medium hover:bg-accent"><MessageCircle className="h-5 w-5" />WhatsApp</a>
            {can("pacientes") && (c.patient_id
              ? <button type="button" onClick={() => { onClose(); openPatient(c.patient_id); }} className="flex flex-col items-center gap-1.5 rounded-2xl bg-secondary py-3 text-[13px] font-medium hover:bg-accent"><UserRound className="h-5 w-5" />Ficha</button>
              : <button type="button" onClick={crearFicha} disabled={!!busy} className="flex flex-col items-center gap-1.5 rounded-2xl bg-secondary py-3 text-[13px] font-medium hover:bg-accent">{busy === "ficha" ? <Spinner /> : <UserPlus className="h-5 w-5" />}Crear ficha</button>)}
          </div>
          <dl className="divide-y rounded-2xl border text-[15px]">
            <div className="flex justify-between gap-4 px-4 py-3"><dt className="text-muted-foreground">Teléfono</dt><dd className="tabular">{c.phone || "—"}</dd></div>
            {c.email && <div className="flex justify-between gap-4 px-4 py-3"><dt className="text-muted-foreground">Email</dt><dd className="truncate">{c.email}</dd></div>}
            {c.notes && <div className="px-4 py-3"><dt className="text-muted-foreground">Lo que nos cuenta</dt><dd className="mt-1 whitespace-pre-line">{c.notes}</dd></div>}
          </dl>
          {c.status === "esperando" && <p className="rounded-2xl bg-ambar/10 p-4 text-sm text-ambar">Le hemos mandado el enlace. Cuando lo pulse, la cita se confirma sola y te avisamos.</p>}
          {c.status === "pendiente" && settings && !settings.wa_on && <p className="text-sm text-muted-foreground">Al aceptar se abre WhatsApp con el mensaje y el enlace de confirmación ya escritos: solo tienes que enviarlo.</p>}
          {puede && <button type="button" onClick={borrar} className="flex items-center gap-2 text-sm text-muted-foreground hover:text-destructive"><Trash2 className="h-4 w-4" />Borrar la cita</button>}
        </div>
      )}
    </Hoja>
  );
}
