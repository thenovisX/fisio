import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { addDays, api, DIAS_C, endOf, MESES, norm, parseISO, todayISO, type Cita, type Paciente } from "@/lib/api";
import { useApp } from "@/lib/store";
import { cn } from "@/lib/utils";
import { Avatar, Chip, Chips, Field, Hoja, inputCls, Spinner } from "./bits";

/* «Nueva cita»: todo en una hoja, de arriba abajo: quién, qué tratamiento, qué día y a qué hora.
 * Las horas que se ofrecen son las que de verdad tienen hueco para la duración de ese tratamiento. */

interface Slot { time: string; left: number }

export function NuevaCita() {
  const { nuevaCitaState: pre, cerrarNuevaCita, carta, can, bump, settings } = useApp();
  const open = !!pre;
  const edit: Cita | undefined = pre?.edit;
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [patientId, setPatientId] = useState(0);
  const [serviceId, setServiceId] = useState(0);
  const [date, setDate] = useState(todayISO());
  const [time, setTime] = useState("");
  const [notes, setNotes] = useState("");
  const [q, setQ] = useState("");
  const [pacientes, setPacientes] = useState<Paciente[]>([]);
  const [slots, setSlots] = useState<Slot[] | null>(null);
  const [reason, setReason] = useState("");
  const [saving, setSaving] = useState(false);
  const [otraHora, setOtraHora] = useState(false);

  const servicios = useMemo(() => carta.flatMap((s) => s.dishes.map((d) => ({ ...d, cat: s.name }))), [carta]);
  const svc = servicios.find((s) => s.id === serviceId);
  const minutes = svc?.duration || edit?.duration || settings?.table_minutes || 45;

  // Al abrir: rellenar con lo que venga (editar una cita, un hueco de la agenda o un paciente)
  useEffect(() => {
    if (!pre) return;
    const e: Cita | undefined = pre.edit;
    setName(e?.name ?? pre.name ?? "");
    setPhone(e?.phone ?? pre.phone ?? "");
    setEmail(e?.email ?? "");
    setPatientId(e?.patient_id ?? pre.patient_id ?? 0);
    setServiceId(e?.service_id ?? pre.service_id ?? 0);
    setDate(e?.date ?? pre.date ?? todayISO());
    setTime(e?.time ?? pre.time ?? "");
    setNotes(e?.notes ?? "");
    setQ("");
    setOtraHora(false);
    if (can("pacientes")) api<Paciente[]>("patients").then(setPacientes).catch(() => setPacientes([]));
  }, [pre, can]);

  // Horas libres del día para ese tratamiento
  useEffect(() => {
    if (!open) return;
    let alive = true;
    setSlots(null);
    api<{ slots: Slot[]; reason: string }>(`slots_admin&date=${date}&minutes=${minutes}&exclude=${edit?.id || 0}`)
      .then((r) => { if (alive) { setSlots(r.slots); setReason(r.reason); } })
      .catch((e) => { if (alive) { setSlots([]); setReason(e.message); } });
    return () => { alive = false; };
  }, [open, date, minutes, edit?.id]);

  const sugeridos = useMemo(() => {
    if (!q.trim()) return pacientes.slice(0, 12);
    const n = norm(q);
    const digits = q.replace(/\D/g, "");
    return pacientes.filter((p) => norm(p.name).includes(n) || (digits.length > 2 && p.phone.replace(/\D/g, "").includes(digits))).slice(0, 12);
  }, [q, pacientes]);

  const dias = useMemo(() => Array.from({ length: 21 }, (_, i) => addDays(todayISO(), i)), []);
  const cerrado = (d: string) => {
    if (!settings) return false;
    if (settings.closed_dates?.includes(d)) return true;
    const wd = String(((parseISO(d).getDay() + 6) % 7) + 1);
    return !(settings.hours?.[wd] || []).length;
  };

  function elegirPaciente(p: Paciente) {
    setPatientId(p.id);
    setName(p.name);
    setPhone(p.phone);
    setEmail(p.email);
    setQ("");
  }

  async function guardar() {
    if (!name.trim()) return toast.error("Falta el nombre");
    if (phone.replace(/\D/g, "").length < 6) return toast.error("Falta un teléfono");
    if (!time) return toast.error("Elige una hora");
    setSaving(true);
    try {
      await api("reservation_save", { body: { id: edit?.id, name, phone, email, date, time, people: 1, notes, service_id: serviceId, duration: minutes, patient_id: patientId } });
      toast.success(edit ? "Cita cambiada" : "Cita apuntada", { description: `${name} · ${svc?.name || "Cita"} · ${time}` });
      bump();
      cerrarNuevaCita();
    } catch (e: any) {
      toast.error(e.message);
    } finally {
      setSaving(false);
    }
  }

  const libres = (slots || []).filter((s) => s.left > 0);
  const resumen = [svc?.name, time && `${time}–${endOf({ time, duration: minutes })}`].filter(Boolean).join(" · ");

  return (
    <Hoja
      open={open}
      onClose={cerrarNuevaCita}
      title={edit ? "Cambiar cita" : "Nueva cita"}
      subtitle="Quién, qué y cuándo. Nada más."
      footer={
        <div className="flex items-center gap-3">
          <p className="min-w-0 flex-1 truncate text-sm text-muted-foreground">{resumen || "Elige tratamiento y hora"}</p>
          <Button size="lg" onClick={guardar} disabled={saving}>{saving ? <Spinner /> : null}{edit ? "Guardar" : "Apuntar cita"}</Button>
        </div>
      }
    >
      <div className="space-y-7">
        <section>
          <h3 className="mb-3 text-xs font-bold uppercase tracking-[.14em] text-muted-foreground">1 · Paciente</h3>
          {can("pacientes") && !patientId && (
            <>
              <div className="relative mb-3">
                <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input className={cn(inputCls, "pl-11")} placeholder="Buscar paciente por nombre o teléfono" value={q} onChange={(e) => setQ(e.target.value)} />
              </div>
              {sugeridos.length > 0 && (
                <Chips className="mb-4 gap-3">
                  {sugeridos.map((p) => (
                    <button key={p.id} type="button" onClick={() => elegirPaciente(p)} className="flex w-[74px] shrink-0 flex-col items-center gap-1.5 text-center">
                      <Avatar name={p.name} size={56} />
                      <span className="line-clamp-2 text-[12px] leading-tight">{p.name}</span>
                    </button>
                  ))}
                </Chips>
              )}
            </>
          )}
          {patientId > 0 ? (
            <div className="flex items-center gap-3 rounded-2xl bg-secondary p-3">
              <Avatar name={name} size={44} />
              <div className="min-w-0 flex-1"><p className="truncate font-semibold">{name}</p><p className="text-sm text-muted-foreground tabular">{phone}</p></div>
              {!edit && <Button variant="ghost" size="sm" onClick={() => { setPatientId(0); setName(""); setPhone(""); setEmail(""); }}>Cambiar</Button>}
            </div>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Nombre"><input className={inputCls} value={name} onChange={(e) => setName(e.target.value)} autoComplete="off" /></Field>
              <Field label="Teléfono"><input className={inputCls} type="tel" inputMode="tel" value={phone} onChange={(e) => setPhone(e.target.value)} autoComplete="off" /></Field>
            </div>
          )}
        </section>

        <section>
          <h3 className="mb-3 text-xs font-bold uppercase tracking-[.14em] text-muted-foreground">2 · Tratamiento</h3>
          <div className="flex flex-wrap gap-2">
            {servicios.map((s) => (
              <Chip key={s.id} active={serviceId === s.id} onClick={() => { setServiceId(s.id); setTime(""); }} className={cn(!s.available && "opacity-60")}>
                {s.name} <span className="ml-1 opacity-60">{s.duration}′</span>
              </Chip>
            ))}
            {!servicios.length && <p className="text-sm text-muted-foreground">Aún no hay tratamientos. Añádelos en Más → Tratamientos.</p>}
          </div>
        </section>

        <section>
          <h3 className="mb-3 text-xs font-bold uppercase tracking-[.14em] text-muted-foreground">3 · Día y hora</h3>
          <Chips className="mb-4">
            {dias.map((d, i) => {
              const dd = parseISO(d);
              const on = d === date;
              return (
                <button key={d} type="button" onClick={() => { setDate(d); setTime(""); }} aria-pressed={on}
                  className={cn("flex w-[58px] shrink-0 flex-col items-center rounded-2xl py-2.5 transition-colors", on ? "bg-foreground text-background" : "bg-secondary hover:bg-accent", cerrado(d) && !on && "opacity-35")}>
                  <span className="text-[11px] uppercase">{i === 0 ? "hoy" : DIAS_C[dd.getDay()]}</span>
                  <span className="text-xl font-semibold tabular">{dd.getDate()}</span>
                  <span className="text-[10px] uppercase opacity-70">{MESES[dd.getMonth()].slice(0, 3)}</span>
                </button>
              );
            })}
          </Chips>
          {slots === null ? <div className="py-6 text-center"><Spinner /></div> : (
            <>
              {libres.length === 0 && <p className="mb-3 text-sm text-muted-foreground">{reason || "Ese día no queda ningún hueco"}.</p>}
              <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                {(slots || []).map((s) => (
                  <button key={s.time} type="button" onClick={() => setTime(s.time)} aria-pressed={time === s.time}
                    className={cn("rounded-xl py-2.5 text-[15px] font-semibold tabular transition-colors",
                      time === s.time ? "bg-primary text-primary-foreground" : s.left > 0 ? "bg-secondary hover:bg-accent" : "bg-transparent text-muted-foreground/50 line-through ring-1 ring-border")}
                    title={s.left > 0 ? `${s.left} libre${s.left > 1 ? "s" : ""}` : "Completo"}>
                    {s.time}
                  </button>
                ))}
              </div>
              <button type="button" className="mt-3 text-sm text-muted-foreground underline underline-offset-4" onClick={() => setOtraHora((x) => !x)}>Otra hora (fuera de horario)</button>
              {otraHora && <input type="time" className={cn(inputCls, "mt-2 w-40")} value={time} onChange={(e) => setTime(e.target.value)} />}
            </>
          )}
        </section>

        <section>
          <Field label="Notas" hint="opcional"><textarea className={cn(inputCls, "min-h-[84px]")} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Motivo, si viene derivado, si trae pruebas…" /></Field>
        </section>
      </div>
    </Hoja>
  );
}
