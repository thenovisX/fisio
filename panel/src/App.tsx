import { useCallback, useEffect, useState, type ReactNode } from "react";
import { toast } from "sonner";
import { CalendarDays, House, Inbox, LayoutGrid, Plus, Users } from "lucide-react";
import { Toaster } from "@/components/ui/sonner";
import { Button } from "@/components/ui/button";
import { api, isNative, LS, session, setUnauthorizedHandler, type Yo } from "@/lib/api";
import { AppProvider, useApp, type Tab } from "@/lib/store";
import { Avatar, Field, inputCls, Spinner, useWide } from "@/components/bits";
import { NuevaCita } from "@/components/nueva-cita";
import { Hoy } from "@/screens/hoy";
import { Agenda } from "@/screens/agenda";
import { Bandeja } from "@/screens/bandeja";
import { FichaPaciente, Pacientes } from "@/screens/pacientes";
import { Mas } from "@/screens/mas";
import { cn } from "@/lib/utils";

/* Panel del centro de fisioterapia.
 * Móvil: barra de abajo con el botón «+» en el centro para apuntar una cita (como «Subir» en Wallapop).
 * Ordenador: barra lateral oscura con la biblioteca de secciones (como Spotify). */

export default function App() {
  const [logged, setLogged] = useState(!!session.token);
  const out = useCallback(() => { session.token = ""; LS.del("me"); setLogged(false); }, []);
  useEffect(() => { setUnauthorizedHandler(out); }, [out]);
  useEffect(() => {
    if (!isNative && "serviceWorker" in navigator && location.protocol === "https:") navigator.serviceWorker.register("sw.js").catch(() => {});
  }, []);
  return (
    <>
      {logged ? <AppProvider onLogout={out}><Shell /></AppProvider> : <Login onIn={() => setLogged(true)} />}
      <Toaster position="top-center" theme="dark" richColors closeButton />
    </>
  );
}

const TABS: { id: Tab; label: string; icon: ReactNode }[] = [
  { id: "hoy", label: "Hoy", icon: <House /> },
  { id: "agenda", label: "Agenda", icon: <CalendarDays /> },
  { id: "bandeja", label: "Bandeja", icon: <Inbox /> },
  { id: "pacientes", label: "Pacientes", icon: <Users /> },
  { id: "mas", label: "Tratamientos y ajustes", icon: <LayoutGrid /> },
];

function Shell() {
  const { tab, setTab, pending, nuevaCita, patientId, me, settings, can } = useApp();
  const wide = useWide();
  const pantalla = patientId ? <FichaPaciente id={patientId} /> : {
    hoy: <Hoy />, agenda: <Agenda />, bandeja: <Bandeja />, pacientes: <Pacientes />, mas: <Mas />,
  }[tab];

  // Pedir permiso de notificaciones la primera vez (navegador)
  useEffect(() => {
    if (!isNative && "Notification" in window && Notification.permission === "default") {
      const t = setTimeout(() => toast("¿Te avisamos de las citas nuevas?", { duration: 12000, action: { label: "Sí", onClick: () => Notification.requestPermission() } }), 4000);
      return () => clearTimeout(t);
    }
  }, []);

  if (wide) {
    return (
      <div className="flex min-h-screen gap-2 bg-black p-2">
        <aside className="sticky top-2 flex h-[calc(100vh-16px)] w-[272px] shrink-0 flex-col gap-2">
          <div className="rounded-2xl bg-background p-5">
            <p className="font-display text-2xl leading-none">{settings?.name || "Mi centro"}</p>
            <p className="mt-1 text-sm text-muted-foreground">Agenda del centro</p>
          </div>
          <nav className="flex-1 rounded-2xl bg-background p-3">
            {TABS.map((t) => (
              <button key={t.id} type="button" onClick={() => setTab(t.id)}
                className={cn("flex w-full items-center gap-4 rounded-xl px-3 py-3 text-[15px] font-semibold transition-colors [&_svg]:h-6 [&_svg]:w-6",
                  tab === t.id && !patientId ? "bg-secondary text-foreground" : "text-muted-foreground hover:text-foreground")}>
                {t.icon}<span className="flex-1 text-left">{t.label}</span>
                {t.id === "bandeja" && pending > 0 && <span className="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">{pending}</span>}
              </button>
            ))}
            {can("reservas") && <Button size="lg" className="mt-4 w-full" onClick={() => nuevaCita()}><Plus />Nueva cita</Button>}
          </nav>
          <div className="flex items-center gap-3 rounded-2xl bg-background p-4">
            <Avatar name={me?.name || me?.username || "?"} size={36} />
            <div className="min-w-0"><p className="truncate text-sm font-semibold">{me?.name || me?.username}</p><p className="text-xs text-muted-foreground">{me?.role === "staff" ? "Equipo" : "Administrador"}</p></div>
          </div>
        </aside>
        <main className="min-w-0 flex-1 rounded-2xl bg-background bg-[radial-gradient(ellipse_at_top_left,hsl(10_100%_61%/.10),transparent_45%)] px-10 py-6">
          <div className="mx-auto max-w-6xl">{pantalla}</div>
        </main>
        <NuevaCita />
      </div>
    );
  }

  const MOVIL: Tab[] = ["hoy", "agenda", "bandeja", "pacientes"];
  return (
    <div className="min-h-screen pb-28">
      <header className="pt-safe sticky top-0 z-30 flex items-center justify-between bg-background/85 px-5 py-2.5 backdrop-blur-xl">
        <p className="truncate font-display text-lg">{settings?.name || "Mi centro"}</p>
        <button type="button" onClick={() => setTab("mas")} aria-label="Tratamientos y ajustes" className={cn("rounded-full", tab === "mas" && "ring-2 ring-primary ring-offset-2 ring-offset-background")}>
          <Avatar name={me?.name || me?.username || "?"} size={34} />
        </button>
      </header>
      <main className="px-5 pt-6">{pantalla}</main>
      <nav className="pb-safe fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 backdrop-blur-xl" aria-label="Secciones">
        <div className="mx-auto grid max-w-md grid-cols-5 items-end px-2 pt-1.5">
          {MOVIL.slice(0, 2).map((id) => <TabBtn key={id} id={id} />)}
          <div className="flex justify-center">
            {can("reservas") && (
              <button type="button" onClick={() => nuevaCita()} aria-label="Nueva cita"
                className="-mt-6 grid h-[58px] w-[58px] place-items-center rounded-full bg-primary text-primary-foreground shadow-lg shadow-primary/30 ring-4 ring-background transition-transform active:scale-95">
                <Plus className="h-7 w-7" strokeWidth={2.6} />
              </button>
            )}
          </div>
          {MOVIL.slice(2).map((id) => <TabBtn key={id} id={id} />)}
        </div>
      </nav>
      <NuevaCita />
    </div>
  );

  function TabBtn({ id }: { id: Tab }) {
    const t = TABS.find((x) => x.id === id)!;
    const on = tab === id && !patientId;
    return (
      <button type="button" onClick={() => setTab(id)} aria-current={on ? "page" : undefined}
        className={cn("relative flex flex-col items-center gap-1 pb-2 pt-1 text-[11px] font-semibold [&_svg]:h-6 [&_svg]:w-6", on ? "text-foreground" : "text-muted-foreground")}>
        {t.icon}{t.label}
        {id === "bandeja" && pending > 0 && <span className="absolute right-[18%] top-0 min-w-[18px] rounded-full bg-primary px-1 text-center text-[10px] leading-[18px] text-primary-foreground">{pending}</span>}
      </button>
    );
  }
}

/* ---------- Entrar ---------- */
function Login({ onIn }: { onIn: () => void }) {
  const [base, setBase] = useState(session.base);
  const [user, setUser] = useState("");
  const [pass, setPass] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");
  const [modo, setModo] = useState<"entrar" | "olvido" | "reset">(() => location.hash.startsWith("#reset=") ? "reset" : "entrar");
  const [msg, setMsg] = useState("");
  const token = location.hash.startsWith("#reset=") ? location.hash.slice(7) : "";

  async function entrar(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setErr("");
    try {
      if (isNative || base !== session.base) session.base = base.trim().replace(/\/+$/, "").replace(/^(?!https?:\/\/)/, "https://");
      const r = await api<{ token: string; me: Yo }>("login", { body: { username: user, password: pass } });
      session.token = r.token;
      LS.set("me", JSON.stringify(r.me));
      onIn();
    } catch (e: any) { setErr(e.message); } finally { setBusy(false); }
  }
  async function olvido(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setErr("");
    try { await api("forgot", { body: { username: user } }); setMsg("Si el usuario existe, te hemos enviado un email con un enlace para cambiar la contraseña. Caduca en 1 hora."); }
    catch (e: any) { setErr(e.message); } finally { setBusy(false); }
  }
  async function reset(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true); setErr("");
    try { await api("reset", { body: { t: token, password: pass } }); history.replaceState(null, "", location.pathname); setModo("entrar"); setPass(""); setMsg("Contraseña cambiada. Ya puedes entrar."); }
    catch (e: any) { setErr(e.message); } finally { setBusy(false); }
  }

  return (
    <div className="relative grid min-h-screen place-items-center overflow-hidden px-5 py-10">
      <div className="pointer-events-none absolute -left-40 -top-40 h-[520px] w-[520px] rounded-full bg-primary/25 blur-[120px]" />
      <div className="pointer-events-none absolute -bottom-52 -right-40 h-[520px] w-[520px] rounded-full bg-salvia/20 blur-[120px]" />
      <form onSubmit={modo === "entrar" ? entrar : modo === "olvido" ? olvido : reset} className="relative w-full max-w-sm">
        <p className="text-xs font-bold uppercase tracking-[.18em] text-primary">Panel del centro</p>
        <h1 className="mt-2 font-display text-5xl leading-[.95]">{modo === "entrar" ? <>Tu agenda,<br /><em className="font-light">en un toque</em></> : modo === "olvido" ? "¿Olvidaste la contraseña?" : "Contraseña nueva"}</h1>
        <div className="mt-8 grid gap-4">
          {modo === "entrar" && (isNative || !session.base) && <Field label="Dirección de la web"><input className={inputCls} value={base} onChange={(e) => setBase(e.target.value)} placeholder="https://tu-centro.com" inputMode="url" autoCapitalize="off" /></Field>}
          {modo !== "reset" && <Field label={modo === "olvido" ? "Usuario o email" : "Usuario"}><input className={inputCls} value={user} onChange={(e) => setUser(e.target.value)} autoComplete="username" autoCapitalize="off" required /></Field>}
          {modo !== "olvido" && <Field label={modo === "reset" ? "Nueva contraseña" : "Contraseña"}><input className={inputCls} type="password" value={pass} onChange={(e) => setPass(e.target.value)} autoComplete={modo === "reset" ? "new-password" : "current-password"} required /></Field>}
          {err && <p className="text-sm text-destructive" role="alert">{err}</p>}
          {msg && <p className="text-sm text-salvia" role="status">{msg}</p>}
          <Button size="lg" type="submit" disabled={busy}>{busy && <Spinner />}{modo === "entrar" ? "Entrar" : modo === "olvido" ? "Enviar enlace" : "Guardar contraseña"}</Button>
          <button type="button" className="text-sm text-muted-foreground underline underline-offset-4" onClick={() => { setModo(modo === "entrar" ? "olvido" : "entrar"); setErr(""); setMsg(""); }}>
            {modo === "entrar" ? "He olvidado la contraseña" : "Volver a entrar"}
          </button>
        </div>
      </form>
    </div>
  );
}
