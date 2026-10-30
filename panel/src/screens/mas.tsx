import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import { ChevronRight, Clock, Globe, Image as ImageIcon, KeyRound, LogOut, Plus, Settings2, Sparkles, Store, Trash2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { api, euro, session, type Tratamiento } from "@/lib/api";
import { useApp } from "@/lib/store";
import { Chip, Field, Hoja, inputCls, paleta, SectionTitle, Spinner } from "@/components/bits";
import { cn } from "@/lib/utils";

/* «Más»: los tratamientos como anuncios de Wallapop (foto, precio, duración) y los ajustes en tarjetas grandes. */

type Panel = null | "centro" | "horario" | "agenda" | "fotos" | "clave";

export function Mas() {
  const { carta, can, logout, me, settings, reloadCarta } = useApp();
  const [edit, setEdit] = useState<Partial<Tratamiento> | null>(null);
  const [panel, setPanel] = useState<Panel>(null);
  const [cat, setCat] = useState(0);
  const todos = useMemo(() => carta.flatMap((s) => s.dishes.map((d) => ({ ...d, cat: s.name }))), [carta]);
  const visibles = cat ? todos.filter((d) => d.category_id === cat) : todos;

  return (
    <div>
      <div className="flex items-end justify-between gap-3">
        <h1 className="font-display text-[40px] leading-none">Tratamientos</h1>
        {can("carta") && <Button onClick={() => setEdit({ category_id: cat || carta[0]?.id, available: true, duration: settings?.table_minutes || 45 })}><Plus />Nuevo</Button>}
      </div>
      <div className="no-scrollbar -mx-5 mt-4 flex gap-2 overflow-x-auto px-5">
        <Chip active={!cat} onClick={() => setCat(0)}>Todos · {todos.length}</Chip>
        {carta.map((s) => <Chip key={s.id} active={cat === s.id} onClick={() => setCat(s.id)}>{s.name}</Chip>)}
        {can("carta") && <Chip onClick={async () => { const name = prompt("Nombre de la sección (por ejemplo: Deporte)"); if (!name) return; try { await api("category_save", { body: { name } }); await reloadCarta(); } catch (e: any) { toast.error(e.message); } }}><Plus className="inline h-4 w-4" /> Sección</Chip>}
      </div>

      <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
        {visibles.map((d) => {
          const [a, b] = paleta(d.name);
          return (
            <button key={d.id} type="button" onClick={() => can("carta") && setEdit(d)} className={cn("group overflow-hidden rounded-3xl bg-card text-left ring-1 ring-border transition-transform hover:-translate-y-0.5", !d.available && "opacity-50")}>
              <div className="relative aspect-[4/3] overflow-hidden">
                {d.photo ? <img src={d.photo} alt="" className="h-full w-full object-cover" loading="lazy" />
                  : <div className="flex h-full w-full items-end p-3" style={{ background: `linear-gradient(140deg, ${a}, ${b})` }}><span className="font-display text-3xl leading-none text-black/70">{d.name.split(" ")[0]}</span></div>}
                <span className="absolute left-2 top-2 rounded-full bg-black/60 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur"><Clock className="mr-1 inline h-3 w-3" />{d.duration} min</span>
                {d.featured && <span className="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-primary text-primary-foreground"><Sparkles className="h-3.5 w-3.5" /></span>}
              </div>
              <div className="p-3.5">
                <p className="text-lg font-bold tabular">{d.price > 0 ? euro(d.price) : "Consultar"}</p>
                <p className="line-clamp-2 text-[14px] leading-snug">{d.name}</p>
                <p className="mt-1 text-xs text-muted-foreground">{d.cat}{!d.available && " · oculto en la web"}</p>
              </div>
            </button>
          );
        })}
      </div>

      <SectionTitle>Ajustes</SectionTitle>
      <div className="grid gap-3 sm:grid-cols-2">
        {can("ajustes") && <Ajuste icon={<Store />} title="El centro" text="Nombre, frase, presentación y contacto" onClick={() => setPanel("centro")} />}
        {can("ajustes") && <Ajuste icon={<Clock />} title="Horario" text="Días y horas en los que se puede pedir cita" onClick={() => setPanel("horario")} />}
        {can("ajustes") && <Ajuste icon={<Settings2 />} title="Citas online" text="Fisioterapeutas a la vez, duración, antelación y normas" onClick={() => setPanel("agenda")} />}
        {can("ajustes") && <Ajuste icon={<ImageIcon />} title="Fotos de la web" text="El centro, el equipo y la imagen para redes" onClick={() => setPanel("fotos")} />}
        <Ajuste icon={<KeyRound />} title="Mi contraseña" text={me ? `Has entrado como ${me.username}` : ""} onClick={() => setPanel("clave")} />
        {can("ajustes") && <Ajuste icon={<Globe />} title="Ajustes avanzados" text="Usuarios, idiomas, WhatsApp automático, email, bonos y avisos legales" href="avanzado/" />}
        <Ajuste icon={<Globe />} title="Ver la web" text={session.base.replace(/^https?:\/\//, "")} href={session.base + "/"} />
        <Ajuste icon={<LogOut />} title="Cerrar sesión" text="" onClick={() => { if (confirm("¿Cerrar sesión en este dispositivo?")) logout(); }} />
      </div>

      <TratamientoForm t={edit} onClose={() => setEdit(null)} />
      <CentroForm open={panel === "centro"} onClose={() => setPanel(null)} />
      <HorarioForm open={panel === "horario"} onClose={() => setPanel(null)} />
      <AgendaForm open={panel === "agenda"} onClose={() => setPanel(null)} />
      <Fotos open={panel === "fotos"} onClose={() => setPanel(null)} />
      <Clave open={panel === "clave"} onClose={() => setPanel(null)} />
    </div>
  );
}

function Ajuste({ icon, title, text, onClick, href }: { icon: ReactNode; title: string; text: string; onClick?: () => void; href?: string }) {
  const inner = (
    <>
      <span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-secondary text-primary [&_svg]:h-5 [&_svg]:w-5">{icon}</span>
      <span className="min-w-0 flex-1"><span className="block font-semibold">{title}</span>{text && <span className="block truncate text-sm text-muted-foreground">{text}</span>}</span>
      <ChevronRight className="h-4 w-4 text-muted-foreground" />
    </>
  );
  const cls = "flex items-center gap-4 rounded-3xl bg-card p-4 text-left ring-1 ring-border transition-colors hover:bg-secondary";
  return href ? <a className={cls} href={href} target={href.startsWith("http") ? "_blank" : undefined} rel="noopener">{inner}</a> : <button type="button" className={cls} onClick={onClick}>{inner}</button>;
}

/* ---------- Tratamiento ---------- */
function TratamientoForm({ t, onClose }: { t: Partial<Tratamiento> | null; onClose: () => void }) {
  const { carta, reloadCarta } = useApp();
  const [v, setV] = useState<Partial<Tratamiento>>({});
  const [foto, setFoto] = useState<File | null>(null);
  const [saving, setSaving] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);
  useEffect(() => { setV({}); setFoto(null); }, [t]);
  const cur = { ...t, ...v } as Partial<Tratamiento>;
  async function save() {
    setSaving(true);
    try {
      const f = new FormData();
      if (cur.id) f.append("id", String(cur.id));
      f.append("name", cur.name || "");
      f.append("description", cur.description || "");
      f.append("price", String(cur.price ?? 0));
      f.append("duration", String(cur.duration || 45));
      f.append("category_id", String(cur.category_id || carta[0]?.id || 0));
      f.append("available", cur.available === false ? "0" : "1");
      f.append("featured", cur.featured ? "1" : "0");
      f.append("allergens", "");
      if (foto) f.append("photo", foto);
      await api("dish_save", { form: f });
      await reloadCarta();
      toast.success("Tratamiento guardado");
      onClose();
    } catch (e: any) { toast.error(e.message); } finally { setSaving(false); }
  }
  async function borrar() {
    if (!cur.id || !confirm(`¿Borrar «${cur.name}»?`)) return;
    try { await api("dish_delete", { body: { id: cur.id } }); await reloadCarta(); onClose(); } catch (e: any) { toast.error(e.message); }
  }
  return (
    <Hoja open={!!t} onClose={onClose} title={t?.id ? "Editar tratamiento" : "Nuevo tratamiento"}
      footer={<div className="flex gap-2">{t?.id && <Button variant="ghost" onClick={borrar}><Trash2 />Borrar</Button>}<Button size="lg" className="flex-1" onClick={save} disabled={saving}>{saving && <Spinner />}Publicar</Button></div>}>
      <div className="grid gap-5">
        <button type="button" onClick={() => fileRef.current?.click()} className="relative flex aspect-[16/9] w-full items-center justify-center overflow-hidden rounded-3xl border border-dashed bg-background/50 text-muted-foreground hover:text-foreground">
          {foto ? <img src={URL.createObjectURL(foto)} alt="" className="absolute inset-0 h-full w-full object-cover" /> : cur.photo ? <img src={cur.photo} alt="" className="absolute inset-0 h-full w-full object-cover" /> : <span className="flex flex-col items-center gap-2"><ImageIcon className="h-7 w-7" />Añadir foto (opcional)</span>}
        </button>
        <input ref={fileRef} type="file" accept="image/*" hidden onChange={(e) => setFoto(e.target.files?.[0] || null)} />
        <Field label="Nombre"><input className={inputCls} value={cur.name || ""} onChange={(e) => setV((x) => ({ ...x, name: e.target.value }))} placeholder="Fisioterapia deportiva" /></Field>
        <div className="grid grid-cols-2 gap-4">
          <Field label="Precio (€)"><input className={inputCls} inputMode="decimal" value={String(cur.price ?? "")} onChange={(e) => setV((x) => ({ ...x, price: e.target.value as unknown as number }))} /></Field>
          <Field label="Duración"><select className={inputCls} value={cur.duration || 45} onChange={(e) => setV((x) => ({ ...x, duration: Number(e.target.value) }))}>
            {[15, 20, 25, 30, 40, 45, 50, 55, 60, 75, 90, 120].map((m) => <option key={m} value={m}>{m} min</option>)}
          </select></Field>
        </div>
        <Field label="Sección"><div className="flex flex-wrap gap-2">{carta.map((s) => <Chip key={s.id} active={cur.category_id === s.id} onClick={() => setV((x) => ({ ...x, category_id: s.id }))}>{s.name}</Chip>)}</div></Field>
        <Field label="Descripción" hint="la ve el paciente en la web"><textarea className={cn(inputCls, "min-h-[90px]")} value={cur.description || ""} onChange={(e) => setV((x) => ({ ...x, description: e.target.value }))} /></Field>
        <label className="flex items-center justify-between gap-4 rounded-2xl bg-secondary px-4 py-3"><span><span className="block font-semibold">Se puede reservar en la web</span><span className="text-sm text-muted-foreground">Apágalo para ocultarlo sin borrarlo</span></span><Switch checked={cur.available !== false} onCheckedChange={(b) => setV((x) => ({ ...x, available: b }))} /></label>
        <label className="flex items-center justify-between gap-4 rounded-2xl bg-secondary px-4 py-3"><span><span className="block font-semibold">Destacado</span><span className="text-sm text-muted-foreground">Sale con una estrella en la portada</span></span><Switch checked={!!cur.featured} onCheckedChange={(b) => setV((x) => ({ ...x, featured: b }))} /></label>
      </div>
    </Hoja>
  );
}

/* ---------- Ajustes: formulario genérico sobre settings_save ---------- */
function useAjustes(open: boolean, keys: string[]) {
  const { settings, reloadSettings } = useApp();
  const [v, setV] = useState<Record<string, any>>({});
  const [saving, setSaving] = useState(false);
  useEffect(() => { if (open && settings) setV(Object.fromEntries(keys.map((k) => [k, settings[k]]))); }, [open, settings]); // eslint-disable-line react-hooks/exhaustive-deps
  async function save(onDone: () => void) {
    setSaving(true);
    try { await api("settings_save", { body: v }); await reloadSettings(); toast.success("Guardado"); onDone(); } catch (e: any) { toast.error(e.message); } finally { setSaving(false); }
  }
  return { v, setV, saving, save, set: (k: string) => (e: { target: { value: string } }) => setV((x) => ({ ...x, [k]: e.target.value })) };
}

function CentroForm({ open, onClose }: { open: boolean; onClose: () => void }) {
  const a = useAjustes(open, ["name", "tagline", "description", "phone", "whatsapp", "email", "notify_email", "address", "city", "instagram", "owner_name", "owner_nif"]);
  const campo = (k: string, label: string, hint?: string, type = "text") => <Field label={label} hint={hint}><input className={inputCls} type={type} value={a.v[k] ?? ""} onChange={a.set(k)} /></Field>;
  return (
    <Hoja open={open} onClose={onClose} title="El centro" footer={<Button size="lg" className="w-full" onClick={() => a.save(onClose)} disabled={a.saving}>Guardar</Button>}>
      <div className="grid gap-4">
        {campo("name", "Nombre")}
        {campo("tagline", "Frase corta", "sale bajo el título de la portada")}
        <Field label="Presentación" hint="el texto grande de «Cómo trabajamos»"><textarea className={cn(inputCls, "min-h-[120px]")} value={a.v.description ?? ""} onChange={a.set("description")} /></Field>
        <div className="grid gap-4 sm:grid-cols-2">{campo("phone", "Teléfono", "", "tel")}{campo("whatsapp", "WhatsApp", "", "tel")}</div>
        <div className="grid gap-4 sm:grid-cols-2">{campo("email", "Email público", "", "email")}{campo("notify_email", "Email para avisos de citas", "", "email")}</div>
        <div className="grid gap-4 sm:grid-cols-2">{campo("address", "Dirección")}{campo("city", "Ciudad")}</div>
        {campo("instagram", "Instagram", "usuario o enlace")}
        <div className="grid gap-4 sm:grid-cols-2">{campo("owner_name", "Titular", "para el aviso legal")}{campo("owner_nif", "NIF")}</div>
      </div>
    </Hoja>
  );
}

const SEMANA: [string, string][] = [["1", "Lunes"], ["2", "Martes"], ["3", "Miércoles"], ["4", "Jueves"], ["5", "Viernes"], ["6", "Sábado"], ["7", "Domingo"]];
function HorarioForm({ open, onClose }: { open: boolean; onClose: () => void }) {
  const a = useAjustes(open, ["hours"]);
  const h: Record<string, [string, string][]> = a.v.hours || {};
  const put = (d: string, list: [string, string][]) => a.setV((x) => ({ ...x, hours: { ...(x.hours || {}), [d]: list } }));
  return (
    <Hoja open={open} onClose={onClose} title="Horario" subtitle="Una o dos franjas por día. Sin franjas, ese día está cerrado."
      footer={<Button size="lg" className="w-full" onClick={() => a.save(onClose)} disabled={a.saving}>Guardar horario</Button>}>
      <div className="divide-y">
        {SEMANA.map(([d, nombre]) => {
          const list = h[d] || [];
          return (
            <div key={d} className="py-4">
              <div className="flex items-center justify-between">
                <p className="font-semibold">{nombre}</p>
                <Switch checked={list.length > 0} onCheckedChange={(on) => put(d, on ? [["09:00", "14:00"]] : [])} />
              </div>
              {list.map((r, i) => (
                <div key={i} className="mt-2 flex items-center gap-2">
                  <input type="time" className={cn(inputCls, "py-2")} value={r[0]} onChange={(e) => put(d, list.map((x, j) => j === i ? [e.target.value, x[1]] : x))} />
                  <span className="text-muted-foreground">a</span>
                  <input type="time" className={cn(inputCls, "py-2")} value={r[1]} onChange={(e) => put(d, list.map((x, j) => j === i ? [x[0], e.target.value] : x))} />
                  <Button variant="ghost" size="icon" onClick={() => put(d, list.filter((_, j) => j !== i))} aria-label="Quitar franja"><X /></Button>
                </div>
              ))}
              {list.length > 0 && list.length < 3 && <Button variant="ghost" size="sm" className="mt-1" onClick={() => put(d, [...list, ["16:00", "20:00"]])}><Plus />Otra franja</Button>}
            </div>
          );
        })}
      </div>
    </Hoja>
  );
}

function AgendaForm({ open, onClose }: { open: boolean; onClose: () => void }) {
  const a = useAjustes(open, ["capacity", "table_minutes", "slot_minutes", "min_notice", "days_ahead", "policies"]);
  const num = (k: string, label: string, hint: string, opts: number[], fmt: (n: number) => string) => (
    <Field label={label} hint={hint}>
      <div className="flex flex-wrap gap-2">{opts.map((n) => <Chip key={n} active={Number(a.v[k]) === n} onClick={() => a.setV((x) => ({ ...x, [k]: n }))}>{fmt(n)}</Chip>)}</div>
    </Field>
  );
  return (
    <Hoja open={open} onClose={onClose} title="Citas online" footer={<Button size="lg" className="w-full" onClick={() => a.save(onClose)} disabled={a.saving}>Guardar</Button>}>
      <div className="grid gap-6">
        {num("capacity", "Citas a la vez", "fisioterapeutas o cabinas", [1, 2, 3, 4, 5, 6], (n) => String(n))}
        {num("slot_minutes", "Las citas empiezan cada", "", [10, 15, 20, 30, 60], (n) => n + " min")}
        {num("table_minutes", "Duración si el tratamiento no dice otra", "", [30, 45, 50, 60], (n) => n + " min")}
        {num("min_notice", "Antelación mínima", "", [0, 60, 120, 240, 1440], (n) => n === 0 ? "Ninguna" : n < 1440 ? n / 60 + " h" : "1 día")}
        {num("days_ahead", "Se puede pedir con hasta", "", [14, 30, 60, 90], (n) => n + " días")}
        <Field label="Normas del centro" hint="una por línea: «Título: texto»"><textarea className={cn(inputCls, "min-h-[150px]")} value={a.v.policies ?? ""} onChange={a.set("policies")} /></Field>
      </div>
    </Hoja>
  );
}

function Fotos({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { settings, reloadSettings } = useApp();
  const [busy, setBusy] = useState("");
  const list: { key: string; label: string; url: string; custom: boolean }[] = settings?.web_photos || [];
  async function subir(key: string, file: File) {
    setBusy(key);
    try { const f = new FormData(); f.append("key", key); f.append("photo", file); await api("web_photo", { form: f }); await reloadSettings(); toast.success("Foto cambiada"); }
    catch (e: any) { toast.error(e.message); } finally { setBusy(""); }
  }
  async function quitar(key: string) {
    setBusy(key);
    try { await api("web_photo_reset", { body: { key } }); await reloadSettings(); } catch (e: any) { toast.error(e.message); } finally { setBusy(""); }
  }
  return (
    <Hoja open={open} onClose={onClose} title="Fotos de la web" subtitle="Sin foto, la web pinta las fibras de colores del diseño.">
      <div className="grid gap-4 sm:grid-cols-2">
        {list.map((p) => (
          <div key={p.key} className="overflow-hidden rounded-3xl bg-secondary">
            <div className="relative aspect-[4/3] bg-background/50">
              {p.url ? <img src={p.url} alt="" className="h-full w-full object-cover" /> : <div className="grid h-full place-items-center text-sm text-muted-foreground">Sin foto</div>}
              {busy === p.key && <div className="absolute inset-0 grid place-items-center bg-black/50"><Spinner /></div>}
            </div>
            <div className="flex items-center gap-2 p-3">
              <p className="min-w-0 flex-1 truncate font-semibold">{p.label}</p>
              {p.custom && <Button variant="ghost" size="sm" onClick={() => quitar(p.key)}>Quitar</Button>}
              <label className="inline-flex h-9 cursor-pointer items-center rounded-full bg-foreground px-3.5 text-[13px] font-semibold text-background">Cambiar<input type="file" accept="image/*" hidden onChange={(e) => e.target.files?.[0] && subir(p.key, e.target.files[0])} /></label>
            </div>
          </div>
        ))}
      </div>
    </Hoja>
  );
}

function Clave({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [cur, setCur] = useState("");
  const [nueva, setNueva] = useState("");
  const [saving, setSaving] = useState(false);
  async function save() {
    setSaving(true);
    try { await api("password", { body: { current: cur, new: nueva } }); toast.success("Contraseña cambiada"); setCur(""); setNueva(""); onClose(); }
    catch (e: any) { toast.error(e.message); } finally { setSaving(false); }
  }
  return (
    <Hoja open={open} onClose={onClose} title="Mi contraseña" footer={<Button size="lg" className="w-full" onClick={save} disabled={saving}>Cambiar contraseña</Button>}>
      <div className="grid gap-4">
        <Field label="Contraseña actual"><input className={inputCls} type="password" autoComplete="current-password" value={cur} onChange={(e) => setCur(e.target.value)} /></Field>
        <Field label="Nueva" hint="mínimo 8 caracteres"><input className={inputCls} type="password" autoComplete="new-password" value={nueva} onChange={(e) => setNueva(e.target.value)} /></Field>
      </div>
    </Hoja>
  );
}
