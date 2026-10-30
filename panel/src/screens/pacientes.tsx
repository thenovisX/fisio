import { useMemo, useState } from "react";
import { toast } from "sonner";
import { ArrowLeft, CalendarPlus, MessageCircle, Minus, Pencil, Phone, Plus, Search, Ticket, Trash2, UserPlus, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { api, fmtShort, norm, relDay, telHref, todayISO, waHref, type Nota, type Paciente } from "@/lib/api";
import { useApp, useLoad } from "@/lib/store";
import { Avatar, Chip, Chips, Empty, Field, Hoja, inputCls, paleta, SectionTitle, Spinner, StatusPill } from "@/components/bits";
import { cn } from "@/lib/utils";

/* Pacientes: la lista con buscador y filtros, y la ficha de cada uno como la página de un artista en Spotify. */

type Filtro = "todos" | "bono" | "proxima" | "sin_volver";

export function Pacientes() {
  const { openPatient, can } = useApp();
  const { data, loading } = useLoad(() => api<Paciente[]>("patients"), []);
  const [q, setQ] = useState("");
  const [f, setF] = useState<Filtro>("todos");
  const [nuevo, setNuevo] = useState(false);

  const lista = useMemo(() => {
    let l = data || [];
    if (q.trim()) {
      const n = norm(q), dg = q.replace(/\D/g, "");
      l = l.filter((p) => norm(p.name).includes(n) || p.tags.some((t) => norm(t).includes(n)) || (dg.length > 2 && p.phone.replace(/\D/g, "").includes(dg)));
    }
    const hace30 = new Date(Date.now() - 30 * 864e5).toISOString().slice(0, 10);
    if (f === "bono") l = l.filter((p) => p.bono_left > 0);
    if (f === "proxima") l = l.filter((p) => p.next).sort((a, b) => (a.next || "").localeCompare(b.next || ""));
    if (f === "sin_volver") l = l.filter((p) => !p.next && p.last && p.last.slice(0, 10) < hace30);
    return l;
  }, [data, q, f]);

  if (!can("pacientes")) return <Empty title="Sin permiso para ver pacientes" text="Pídeselo al administrador del centro." />;

  return (
    <div className="mx-auto max-w-3xl">
      <div className="flex items-end justify-between gap-3">
        <h1 className="font-display text-[40px] leading-none">Pacientes</h1>
        <Button onClick={() => setNuevo(true)}><UserPlus />Nueva ficha</Button>
      </div>
      <div className="relative mt-5">
        <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <input className={cn(inputCls, "rounded-full pl-11")} placeholder="Nombre, teléfono o etiqueta" value={q} onChange={(e) => setQ(e.target.value)} />
      </div>
      <Chips className="mt-4">
        {([["todos", "Todos"], ["proxima", "Con próxima cita"], ["bono", "Con bono"], ["sin_volver", "No vuelven hace 30 días"]] as [Filtro, string][]).map(([k, t]) => (
          <Chip key={k} active={f === k} onClick={() => setF(k)}>{t}</Chip>
        ))}
      </Chips>

      <div className="mt-4">
        {loading && !data ? <div className="py-12 text-center"><Spinner /></div> : lista.length ? (
          <div className="space-y-1">
            {lista.map((p) => (
              <button key={p.id} type="button" onClick={() => openPatient(p.id)} className="flex w-full items-center gap-3 rounded-2xl p-2.5 text-left transition-colors hover:bg-secondary/70">
                <Avatar name={p.name} size={48} />
                <div className="min-w-0 flex-1">
                  <p className="truncate font-semibold">{p.name}</p>
                  <p className="truncate text-[13px] text-muted-foreground">
                    {p.next ? `Próxima: ${relDay(p.next.slice(0, 10))} ${p.next.slice(11)}` : p.last ? `Última: ${fmtShort(p.last.slice(0, 10))}` : "Sin citas todavía"}
                    {p.reason ? ` · ${p.reason}` : ""}
                  </p>
                </div>
                {p.bono_left > 0 && <span className="flex shrink-0 items-center gap-1 rounded-full bg-salvia/15 px-2.5 py-1 text-xs font-semibold text-salvia"><Ticket className="h-3.5 w-3.5" />{p.bono_left}</span>}
              </button>
            ))}
          </div>
        ) : <Empty icon={<Users className="h-8 w-8" />} title={data?.length ? "Nadie con ese filtro" : "Aún no hay fichas"} text="Crea la ficha desde aquí o desde una cita, con un toque en «Crear ficha»." />}
      </div>
      <FichaForm open={nuevo} onClose={() => setNuevo(false)} />
    </div>
  );
}

/** Crear o editar los datos de una ficha. */
export function FichaForm({ open, onClose, p }: { open: boolean; onClose: () => void; p?: Paciente }) {
  const { bump, openPatient } = useApp();
  const [v, setV] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const val = (k: keyof Paciente) => v[k] ?? String((p?.[k] as string) ?? "");
  const set = (k: string) => (e: { target: { value: string } }) => setV((x) => ({ ...x, [k]: e.target.value }));
  async function save() {
    setSaving(true);
    try {
      const tags = (v.tags ?? (p?.tags || []).join(", ")).split(",").map((t) => t.trim()).filter(Boolean);
      const r = await api<Paciente>("patient_save", { body: { id: p?.id, name: val("name"), phone: val("phone"), email: val("email"), birth: val("birth"), reason: val("reason"), history: val("history"), tags } });
      toast.success(p ? "Ficha guardada" : "Ficha creada");
      setV({});
      bump();
      onClose();
      if (!p) openPatient(r.id);
    } catch (e: any) { toast.error(e.message); } finally { setSaving(false); }
  }
  return (
    <Hoja open={open} onClose={onClose} title={p ? "Editar ficha" : "Nueva ficha"} subtitle="Los datos de salud se guardan cifrados."
      footer={<Button size="lg" className="w-full" onClick={save} disabled={saving}>{saving && <Spinner />}Guardar</Button>}>
      <div className="grid gap-4">
        <Field label="Nombre y apellidos"><input className={inputCls} value={val("name")} onChange={set("name")} /></Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Teléfono"><input className={inputCls} type="tel" value={val("phone")} onChange={set("phone")} /></Field>
          <Field label="Fecha de nacimiento" hint="opcional"><input className={inputCls} type="date" value={val("birth")} onChange={set("birth")} /></Field>
        </div>
        <Field label="Email" hint="opcional"><input className={inputCls} type="email" value={val("email")} onChange={set("email")} /></Field>
        <Field label="Motivo de consulta"><input className={inputCls} value={val("reason")} onChange={set("reason")} placeholder="Lumbalgia, esguince de tobillo…" /></Field>
        <Field label="Antecedentes" hint="lesiones, cirugías, medicación, alergias"><textarea className={cn(inputCls, "min-h-[110px]")} value={val("history")} onChange={set("history")} /></Field>
        <Field label="Etiquetas" hint="separadas por comas"><input className={inputCls} value={v.tags ?? (p?.tags || []).join(", ")} onChange={set("tags")} placeholder="runner, embarazo, mutua…" /></Field>
      </div>
    </Hoja>
  );
}

/* ---------- Ficha del paciente ---------- */

const EVA_COLOR = (n: number) => n <= 3 ? "#9DBBA6" : n <= 6 ? "#F2B544" : "#FF5B3A";

function Dolor({ notas }: { notas: Nota[] }) {
  const pts = notas.filter((n) => n.pain >= 0).slice().reverse();
  if (pts.length < 2) return null;
  const W = 640, H = 140, P = 12;
  const x = (i: number) => P + (i * (W - 2 * P)) / (pts.length - 1);
  const y = (v: number) => P + ((10 - v) * (H - 2 * P)) / 10;
  const d = pts.map((n, i) => `${i ? "L" : "M"}${x(i)},${y(n.pain)}`).join(" ");
  const ult = pts[pts.length - 1].pain, pri = pts[0].pain;
  return (
    <div className="rounded-3xl bg-card p-5 ring-1 ring-border">
      <div className="flex items-baseline justify-between">
        <p className="font-semibold">Dolor (EVA)</p>
        <p className="text-sm text-muted-foreground">{pri} → <b className="text-foreground">{ult}</b> {ult < pri ? `· mejora ${pri - ult} puntos` : ""}</p>
      </div>
      <svg viewBox={`0 0 ${W} ${H}`} className="mt-3 h-auto w-full" role="img" aria-label={`Dolor de ${pri} a ${ult} en ${pts.length} sesiones`}>
        <path d={`${d} L${x(pts.length - 1)},${H} L${x(0)},${H} Z`} fill="url(#g)" opacity=".25" />
        <defs><linearGradient id="g" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stopColor="#FF5B3A" /><stop offset="1" stopColor="#FF5B3A" stopOpacity="0" /></linearGradient></defs>
        <path d={d} fill="none" stroke="#EDE8DD" strokeWidth="2" strokeLinejoin="round" />
        {pts.map((n, i) => <circle key={n.id} cx={x(i)} cy={y(n.pain)} r="4.5" fill={EVA_COLOR(n.pain)} />)}
      </svg>
    </div>
  );
}

export function FichaPaciente({ id }: { id: number }) {
  const { closePatient, nuevaCita, bump, version } = useApp();
  const { data: p, loading, reload } = useLoad(() => api<Paciente & { notes: Nota[] }>("patient&id=" + id), [id, version]);
  const [editar, setEditar] = useState(false);
  const [nota, setNota] = useState<Partial<Nota> | null>(null);
  const [bonoOpen, setBonoOpen] = useState(false);

  if (loading && !p) return <div className="py-24 text-center"><Spinner /></div>;
  if (!p) return null;
  const [a, b] = paleta(p.name);
  const notas = p.notes || [];
  const citas = p.appointments || [];
  const proxima = citas.filter((c) => c.date >= todayISO() && ["pendiente", "esperando", "confirmada"].includes(c.status)).sort((x, y) => (x.date + x.time).localeCompare(y.date + y.time))[0];
  const hechas = citas.filter((c) => c.date < todayISO() && c.status === "confirmada").length;

  async function bono(body: Record<string, number>) {
    try { await api("bono", { body: { id, ...body } }); reload(); bump(); } catch (e: any) { toast.error(e.message); }
  }
  async function borrar() {
    if (!confirm(`¿Borrar la ficha de ${p!.name} con todas sus notas? No se puede deshacer.`)) return;
    try { await api("patient_delete", { body: { id } }); toast("Ficha borrada"); bump(); closePatient(); } catch (e: any) { toast.error(e.message); }
  }

  return (
    <div className="-mx-5 -mt-6 lg:-mx-10">
      <header className="relative px-5 pb-6 pt-5 lg:px-10" style={{ background: `linear-gradient(180deg, ${a}cc 0%, ${b}88 55%, hsl(var(--background)) 100%)` }}>
        <Button variant="ghost" size="icon" className="bg-black/30 text-white hover:bg-black/50" onClick={closePatient} aria-label="Volver"><ArrowLeft /></Button>
        <div className="mt-6 flex items-end gap-5">
          <Avatar name={p.name} size={112} className="shadow-2xl shadow-black/50" />
          <div className="min-w-0 pb-1">
            <p className="text-xs font-bold uppercase tracking-[.16em] text-white/80">Paciente</p>
            <h1 className="font-display text-4xl leading-none text-white sm:text-6xl">{p.name}</h1>
            <p className="mt-2 text-sm text-white/80">{hechas} {hechas === 1 ? "sesión" : "sesiones"} · {notas.length} {notas.length === 1 ? "nota" : "notas"}{p.reason ? ` · ${p.reason}` : ""}</p>
          </div>
        </div>
      </header>

      <div className="px-5 lg:px-10">
        <div className="flex flex-wrap items-center gap-2">
          <Button size="lg" className="h-14 w-14 rounded-full p-0" onClick={() => nuevaCita({ patient_id: p.id, name: p.name, phone: p.phone })} aria-label="Nueva cita"><CalendarPlus className="!size-6" /></Button>
          {p.phone && <Button variant="secondary" asChild><a href={telHref(p.phone)}><Phone />Llamar</a></Button>}
          {p.phone && <Button variant="secondary" asChild><a href={waHref(p.phone)} target="_blank" rel="noopener"><MessageCircle />WhatsApp</a></Button>}
          <Button variant="ghost" onClick={() => setEditar(true)}><Pencil />Editar</Button>
        </div>
        {p.tags.length > 0 && <div className="mt-4 flex flex-wrap gap-2">{p.tags.map((t) => <span key={t} className="rounded-full bg-secondary px-3 py-1 text-xs">#{t}</span>)}</div>}

        <div className="mt-6 grid gap-3 lg:grid-cols-2">
          <div className="rounded-3xl bg-card p-5 ring-1 ring-border">
            <div className="flex items-center justify-between">
              <p className="flex items-center gap-2 font-semibold"><Ticket className="h-4 w-4 text-salvia" />Bono</p>
              <Button variant="secondary" size="sm" onClick={() => setBonoOpen(true)}>Vender bono</Button>
            </div>
            {p.bono_total > 0 || p.bono_left > 0 ? (
              <>
                <p className="mt-3 font-display text-5xl tabular">{p.bono_left}<span className="text-2xl text-muted-foreground"> / {Math.max(p.bono_total, p.bono_left)}</span></p>
                <p className="text-sm text-muted-foreground">sesiones que le quedan</p>
                <div className="mt-3 flex gap-1">{Array.from({ length: Math.max(p.bono_total, p.bono_left) }, (_, i) => <span key={i} className={cn("h-2 flex-1 rounded-full", i < p.bono_left ? "bg-salvia" : "bg-muted")} />)}</div>
                <div className="mt-3 flex gap-2">
                  <Button variant="outline" size="sm" onClick={() => bono({ left: Math.max(0, p.bono_left - 1) })}><Minus />Gastar una</Button>
                  <Button variant="ghost" size="sm" onClick={() => bono({ left: p.bono_left + 1 })}><Plus />Devolver una</Button>
                </div>
              </>
            ) : <p className="mt-3 text-sm text-muted-foreground">Sin bono. Al venderle uno, cada nota de sesión puede descontar una.</p>}
          </div>
          <div className="rounded-3xl bg-card p-5 ring-1 ring-border">
            <p className="font-semibold">Próxima cita</p>
            {proxima ? (
              <>
                <p className="mt-3 font-display text-3xl">{relDay(proxima.date)} · {proxima.time}</p>
                <p className="text-sm text-muted-foreground">{proxima.service} · {proxima.duration} min</p>
                <div className="mt-2"><StatusPill status={proxima.status} /></div>
              </>
            ) : <p className="mt-3 text-sm text-muted-foreground">No tiene ninguna. <button className="text-primary underline" onClick={() => nuevaCita({ patient_id: p.id, name: p.name, phone: p.phone })}>Darle cita</button></p>}
          </div>
        </div>

        {(p.history || p.reason) && (
          <div className="mt-3 rounded-3xl bg-card p-5 ring-1 ring-border">
            {p.reason && <><p className="text-xs font-bold uppercase tracking-[.14em] text-muted-foreground">Motivo</p><p className="mt-1">{p.reason}</p></>}
            {p.history && <><p className="mt-4 text-xs font-bold uppercase tracking-[.14em] text-muted-foreground">Antecedentes</p><p className="mt-1 whitespace-pre-line text-foreground/85">{p.history}</p></>}
          </div>
        )}

        <div className="mt-3"><Dolor notas={notas} /></div>

        <SectionTitle action={<Button size="sm" onClick={() => setNota({ date: todayISO(), pain: -1, body: "", service: proxima?.service || citas[0]?.service || "" })}><Plus />Nota de sesión</Button>}>Evolución</SectionTitle>
        {notas.length ? (
          <ol className="relative ml-3 space-y-5 border-l border-border pl-6">
            {notas.map((n) => (
              <li key={n.id} className="relative">
                <span className="absolute -left-[31px] top-1 h-3.5 w-3.5 rounded-full ring-4 ring-background" style={{ background: n.pain >= 0 ? EVA_COLOR(n.pain) : "hsl(var(--muted-foreground))" }} />
                <button type="button" className="w-full text-left" onClick={() => setNota(n)}>
                  <p className="text-sm text-muted-foreground"><span>{fmtShort(n.date)}</span>{n.service ? ` · ${n.service}` : ""}{n.pain >= 0 ? ` · dolor ${n.pain}/10` : ""}{n.author ? ` · ${n.author}` : ""}</p>
                  {n.body && <p className="mt-1 whitespace-pre-line">{n.body}</p>}
                </button>
              </li>
            ))}
          </ol>
        ) : <Empty title="Sin notas todavía" text="Después de cada sesión, apunta qué hiciste y cómo está el dolor. Verás la evolución aquí." />}

        {citas.length > 0 && <>
          <SectionTitle>Citas</SectionTitle>
          <div className="divide-y rounded-3xl bg-card ring-1 ring-border">
            {citas.slice(0, 20).map((c) => (
              <div key={c.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <span className="capitalize tabular">{fmtShort(c.date)} · {c.time}</span>
                <span className="min-w-0 flex-1 truncate text-muted-foreground">{c.service}</span>
                <StatusPill status={c.status} />
              </div>
            ))}
          </div>
        </>}

        <button type="button" onClick={borrar} className="mb-6 mt-10 flex items-center gap-2 text-sm text-muted-foreground hover:text-destructive"><Trash2 className="h-4 w-4" />Borrar la ficha</button>
      </div>

      <FichaForm open={editar} onClose={() => setEditar(false)} p={p} />
      <NotaForm nota={nota} pid={p.id} tieneBono={p.bono_left > 0} onClose={() => setNota(null)} onSaved={reload} />
      <Hoja open={bonoOpen} onClose={() => setBonoOpen(false)} title="Vender bono" subtitle="Se suman a las sesiones que ya tenga.">
        <div className="grid grid-cols-3 gap-3">
          {[5, 10, 20].map((n) => (
            <button key={n} type="button" onClick={() => { bono({ add: n }); setBonoOpen(false); toast.success(`Bono de ${n} sesiones`); }}
              className="rounded-3xl bg-secondary py-6 text-center hover:bg-accent">
              <span className="block font-display text-4xl">{n}</span><span className="text-sm text-muted-foreground">sesiones</span>
            </button>
          ))}
        </div>
      </Hoja>
    </div>
  );
}

function NotaForm({ nota, pid, tieneBono, onClose, onSaved }: { nota: Partial<Nota> | null; pid: number; tieneBono: boolean; onClose: () => void; onSaved: () => void }) {
  const [v, setV] = useState<Partial<Nota>>({});
  const [usar, setUsar] = useState(true);
  const [saving, setSaving] = useState(false);
  const cur = { ...nota, ...v };
  const nueva = !nota?.id;
  async function save() {
    setSaving(true);
    try {
      await api("note_save", { body: { patient_id: pid, id: nota?.id, date: cur.date, service: cur.service, pain: cur.pain, body: cur.body, use_bono: nueva && tieneBono && usar } });
      toast.success("Nota guardada");
      setV({});
      onSaved();
      onClose();
    } catch (e: any) { toast.error(e.message); } finally { setSaving(false); }
  }
  async function borrar() {
    if (!nota?.id || !confirm("¿Borrar esta nota?")) return;
    try { await api("note_delete", { body: { id: nota.id } }); setV({}); onSaved(); onClose(); } catch (e: any) { toast.error(e.message); }
  }
  return (
    <Hoja open={!!nota} onClose={() => { setV({}); onClose(); }} title={nueva ? "Nota de sesión" : "Editar nota"}
      footer={<div className="flex gap-2">{!nueva && <Button variant="ghost" onClick={borrar}><Trash2 />Borrar</Button>}<Button size="lg" className="flex-1" onClick={save} disabled={saving}>{saving && <Spinner />}Guardar nota</Button></div>}>
      <div className="grid gap-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Fecha"><input className={inputCls} type="date" value={cur.date || ""} onChange={(e) => setV((x) => ({ ...x, date: e.target.value }))} /></Field>
          <Field label="Tratamiento"><input className={inputCls} value={cur.service || ""} onChange={(e) => setV((x) => ({ ...x, service: e.target.value }))} /></Field>
        </div>
        <div>
          <p className="mb-2 text-[13px] font-semibold">Dolor hoy <span className="font-normal text-muted-foreground">(0 = nada, 10 = el peor)</span></p>
          <div className="grid grid-cols-11 gap-1">
            {Array.from({ length: 11 }, (_, i) => (
              <button key={i} type="button" onClick={() => setV((x) => ({ ...x, pain: cur.pain === i ? -1 : i }))} aria-pressed={cur.pain === i}
                className={cn("aspect-square rounded-xl text-sm font-semibold tabular transition-transform", cur.pain === i ? "scale-110 text-black" : "bg-secondary")}
                style={cur.pain === i ? { background: EVA_COLOR(i) } : undefined}>{i}</button>
            ))}
          </div>
        </div>
        <Field label="Qué hicimos y cómo va"><textarea className={cn(inputCls, "min-h-[150px]")} value={cur.body || ""} onChange={(e) => setV((x) => ({ ...x, body: e.target.value }))} placeholder="Técnicas, ejercicios para casa, respuesta al tratamiento…" /></Field>
        {nueva && tieneBono && (
          <label className="flex items-center justify-between gap-4 rounded-2xl bg-secondary px-4 py-3">
            <span><span className="block font-semibold">Descontar del bono</span><span className="text-sm text-muted-foreground">Le queda una sesión menos</span></span>
            <Switch checked={usar} onCheckedChange={setUsar} />
          </label>
        )}
      </div>
    </Hoja>
  );
}
