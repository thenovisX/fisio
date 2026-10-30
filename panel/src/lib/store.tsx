import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import { api, LS, type Ajustes, type Cita, type Seccion, type Yo } from "./api";

/* Estado común del panel: quién ha entrado, los ajustes, los tratamientos y el contador de peticiones.
 * Cada pantalla carga sus propios datos con useLoad(); «version» sube cuando algo cambia para que se recarguen. */

export type Tab = "hoy" | "agenda" | "bandeja" | "pacientes" | "mas";

interface Ctx {
  me: Yo | null;
  settings: Ajustes | null;
  carta: Seccion[];
  pending: number;
  waiting: number;
  version: number;
  tab: Tab;
  setTab: (t: Tab) => void;
  bump: () => void;
  reloadSettings: () => Promise<void>;
  reloadCarta: () => Promise<void>;
  can: (perm: string) => boolean;
  isAdmin: boolean;
  logout: () => void;
  /** Abre la hoja «Nueva cita» (opcionalmente con día, hora o paciente ya elegidos). */
  nuevaCita: (pre?: Partial<{ date: string; time: string; patient_id: number; name: string; phone: string; service_id: number; edit: Cita }>) => void;
  nuevaCitaState: null | Record<string, any>;
  cerrarNuevaCita: () => void;
  /** Abre la ficha de un paciente encima de cualquier pestaña. */
  openPatient: (id: number) => void;
  patientId: number | null;
  closePatient: () => void;
}

const C = createContext<Ctx>(null as unknown as Ctx);
export const useApp = () => useContext(C);

export function AppProvider({ children, onLogout }: { children: ReactNode; onLogout: () => void }) {
  const [me, setMe] = useState<Yo | null>(() => { try { return JSON.parse(LS.get("me") || "null"); } catch { return null; } });
  const [settings, setSettings] = useState<Ajustes | null>(null);
  const [carta, setCarta] = useState<Seccion[]>([]);
  const [pending, setPending] = useState(0);
  const [waiting, setWaiting] = useState(0);
  const [version, setVersion] = useState(0);
  const [tab, setTabState] = useState<Tab>(() => (LS.get("tab_fisio") as Tab) || "hoy");
  const [nuevaCitaState, setNC] = useState<null | Record<string, any>>(null);
  const [patientId, setPatientId] = useState<number | null>(null);
  const lastSeq = useRef(Number(LS.get("lastId")) || 0);

  const bump = useCallback(() => setVersion((v) => v + 1), []);
  const setTab = useCallback((t: Tab) => { setTabState(t); LS.set("tab_fisio", t); setPatientId(null); window.scrollTo({ top: 0 }); }, []);
  const reloadSettings = useCallback(async () => { setSettings(await api<Ajustes>("settings")); }, []);
  const reloadCarta = useCallback(async () => { const m = await api<{ categories: Seccion[] }>("admin_menu"); setCarta(m.categories); }, []);

  const isAdmin = !me || me.role === "admin";
  const can = useCallback((p: string) => !me || me.role === "admin" || (me.perms || []).includes(p), [me]);

  useEffect(() => {
    api<{ me: Yo }>("me").then((r) => { setMe(r.me); LS.set("me", JSON.stringify(r.me)); }).catch(() => {});
    reloadSettings().catch((e) => toast.error(e.message));
    reloadCarta().catch(() => {});
  }, [reloadSettings, reloadCarta]);

  // Avisos: cada 30 segundos se mira si hay peticiones nuevas o citas confirmadas o canceladas por el paciente
  useEffect(() => {
    let alive = true;
    async function poll() {
      try {
        const r = await api<{ last_id: number; pending: number; waiting: number; new: Cita[] }>("changes&since=" + lastSeq.current);
        if (!alive) return;
        setPending(r.pending);
        setWaiting(r.waiting);
        if (r.new.length && lastSeq.current > 0) {
          r.new.forEach((c) => {
            const what = c.status === "confirmada" ? "Cita confirmada" : c.status === "cancelada" ? "Cita cancelada" : "Nueva petición de cita";
            const body = `${c.name} · ${c.service || "Cita"} · ${c.date.slice(8)}/${c.date.slice(5, 7)} ${c.time}`;
            toast(what, { description: body });
            notify(what, body, "cita-" + c.id + "-" + c.status);
          });
          setVersion((v) => v + 1);
        }
        lastSeq.current = r.last_id;
        LS.set("lastId", String(r.last_id));
      } catch { /* sin conexión: se reintenta */ }
    }
    poll();
    const t = setInterval(poll, 30000);
    const vis = () => { if (!document.hidden) poll(); };
    document.addEventListener("visibilitychange", vis);
    return () => { alive = false; clearInterval(t); document.removeEventListener("visibilitychange", vis); };
  }, [version]);

  const logout = useCallback(() => {
    api("logout", { body: {} }).catch(() => {});
    onLogout();
  }, [onLogout]);

  const value: Ctx = {
    me, settings, carta, pending, waiting, version, tab, setTab, bump, reloadSettings, reloadCarta, can, isAdmin, logout,
    nuevaCita: (pre = {}) => setNC({ ...pre }), nuevaCitaState, cerrarNuevaCita: () => setNC(null),
    openPatient: (id) => setPatientId(id), patientId, closePatient: () => setPatientId(null),
  };
  return <C.Provider value={value}>{children}</C.Provider>;
}

/** Notificación del sistema (app Android con Capacitor, o navegador con permiso). */
async function notify(title: string, body: string, tag: string) {
  const cap = (window as any).Capacitor;
  const LN = cap?.Plugins?.LocalNotifications;
  if (LN) {
    try { await LN.schedule({ notifications: [{ id: Math.floor(Math.random() * 2e9), title, body }] }); } catch { /* sin permiso */ }
    return;
  }
  if ("Notification" in window && Notification.permission === "granted" && navigator.serviceWorker) {
    try { (await navigator.serviceWorker.ready).showNotification(title, { body, tag, icon: "icons/icon-192.png" }); } catch { /* sin service worker */ }
  }
}

/** Carga datos y los vuelve a cargar cuando cambian las dependencias o sube «version». */
export function useLoad<T>(fn: () => Promise<T>, deps: unknown[]): { data: T | null; loading: boolean; error: string; reload: () => void } {
  const { version } = useApp();
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [n, setN] = useState(0);
  useEffect(() => {
    let alive = true;
    setLoading(true);
    fn().then((d) => { if (alive) { setData(d); setError(""); } }).catch((e) => { if (alive) setError(e.message); }).finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, version, n]);
  return { data, loading, error, reload: () => setN((x) => x + 1) };
}
