/* Conexión con la API de la web (api/?r=…). La sesión va en la cabecera X-Token.
 * Las claves de localStorage ('base', 'token', 'me') son las mismas que usa el panel clásico (avanzado/),
 * así que al abrir los ajustes avanzados no hay que volver a entrar. */

export type Status = "pendiente" | "esperando" | "confirmada" | "rechazada" | "cancelada";

export interface Cita {
  id: number;
  name: string;
  phone: string;
  email: string;
  date: string;
  time: string;
  people: number;
  notes: string;
  status: Status;
  source: "web" | "panel";
  created_at: string;
  customer: boolean;
  cancel_by: string;
  lang: string;
  service_id: number;
  service: string;
  duration: number;
  patient_id: number;
  confirm_url?: string;
  wa_url?: string;
  wa_auto?: boolean;
}

export interface Tratamiento {
  id: number;
  category_id: number;
  name: string;
  description: string;
  price: number;
  photo: string;
  available: boolean;
  featured: boolean;
  position: number;
  duration: number;
}

export interface Seccion {
  id: number;
  name: string;
  position: number;
  dishes: Tratamiento[];
}

export interface Nota {
  id: number;
  date: string;
  service: string;
  pain: number;
  body: string;
  author: string;
  created_at: string;
}

export interface Paciente {
  id: number;
  name: string;
  phone: string;
  email: string;
  birth: string;
  reason: string;
  history: string;
  bono_total: number;
  bono_left: number;
  tags: string[];
  created_at: string;
  updated_at: string;
  visits?: number;
  last?: string;
  next?: string;
  notes?: Nota[] | number;
  last_note?: string;
  appointments?: Cita[];
}

export interface Yo {
  id: number;
  username: string;
  name: string;
  role: "admin" | "staff";
  perms: string[];
}

export type Ajustes = Record<string, any> & {
  name: string;
  hours: Record<string, [string, string][]>;
  closed_dates: string[];
  capacity: number;
  table_minutes: number;
  slot_minutes: number;
};

const LS = {
  get: (k: string) => { try { return localStorage.getItem(k); } catch { return null; } },
  set: (k: string, v: string) => { try { localStorage.setItem(k, v); } catch { /* sin almacenamiento */ } },
  del: (k: string) => { try { localStorage.removeItem(k); } catch { /* sin almacenamiento */ } },
};
export { LS };

const NATIVE = !!(window as any).Capacitor?.isNativePlatform?.();
export const isNative = NATIVE;

/** Dirección de la web. En el navegador, la carpeta de arriba de /panel/; en la app, la que se escribe al entrar. */
export function defaultBase(): string {
  if (NATIVE) return "";
  return new URL("..", location.href).href.replace(/\/$/, "");
}

export const session = {
  get base() { return LS.get("base") || defaultBase(); },
  set base(v: string) { LS.set("base", v.replace(/\/+$/, "")); },
  get token() { return LS.get("token") || ""; },
  set token(v: string) { if (v) LS.set("token", v); else LS.del("token"); },
};

export class ApiError extends Error {
  status: number;
  constructor(msg: string, status: number) { super(msg); this.status = status; }
}

let onUnauthorized: () => void = () => {};
export function setUnauthorizedHandler(fn: () => void) { onUnauthorized = fn; }

export async function api<T = any>(route: string, opts: { body?: any; form?: FormData } = {}): Promise<T> {
  const init: RequestInit = { method: "GET", headers: {} as Record<string, string> };
  const headers = init.headers as Record<string, string>;
  if (session.token) headers["X-Token"] = session.token;
  if (opts.form) { init.method = "POST"; init.body = opts.form; }
  else if (opts.body !== undefined) { init.method = "POST"; headers["Content-Type"] = "application/json"; init.body = JSON.stringify(opts.body); }
  let res: Response;
  try {
    res = await fetch(session.base + "/api/?r=" + route, init);
  } catch {
    throw new ApiError("No hay conexión con la web. Revisa internet.", 0);
  }
  let json: any;
  try { json = await res.json(); } catch {
    throw new ApiError(`La web no responde bien (código ${res.status}). Revisa la dirección.`, res.status);
  }
  if (res.status === 401 && route !== "login") {
    session.token = "";
    onUnauthorized();
    throw new ApiError(json.error || "Vuelve a iniciar sesión", 401);
  }
  if (!json.ok) throw new ApiError(json.error || "Algo ha fallado", res.status);
  return json.data as T;
}

/* ---------- Fechas ---------- */
export const pad = (n: number) => String(n).padStart(2, "0");
export const iso = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const parseISO = (s: string) => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d, 12); };
export const addDays = (s: string, n: number) => { const d = parseISO(s); d.setDate(d.getDate() + n); return iso(d); };
export const todayISO = () => iso(new Date());
export const DIAS = ["domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado"];
export const DIAS_C = ["dom", "lun", "mar", "mié", "jue", "vie", "sáb"];
export const MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
const cap = (t: string) => t.charAt(0).toUpperCase() + t.slice(1);
export const fmtDay = (s: string) => { const d = parseISO(s); return cap(`${DIAS[d.getDay()]} ${d.getDate()} de ${MESES[d.getMonth()]}`); };
export const fmtShort = (s: string) => { const d = parseISO(s); return cap(`${DIAS_C[d.getDay()]} ${d.getDate()} ${MESES[d.getMonth()].slice(0, 3)}`); };
export const relDay = (s: string) => {
  const t = todayISO();
  if (s === t) return "Hoy";
  if (s === addDays(t, 1)) return "Mañana";
  if (s === addDays(t, -1)) return "Ayer";
  return fmtShort(s);
};
export const toMin = (hm: string) => { const [h, m] = hm.split(":").map(Number); return h * 60 + m; };
export const fromMin = (n: number) => `${pad(Math.floor(n / 60) % 24)}:${pad(n % 60)}`;
export const endOf = (c: Pick<Cita, "time" | "duration">) => fromMin(toMin(c.time) + (c.duration || 45));
export const nowMin = () => { const d = new Date(); return d.getHours() * 60 + d.getMinutes(); };
export const euro = (n: number) => Number(n).toLocaleString("es-ES", { style: "currency", currency: "EUR" });

/* ---------- Contacto ---------- */
export const telHref = (t: string) => "tel:" + String(t).replace(/[^\d+]/g, "");
export const waHref = (t: string, text = "") => {
  let n = String(t).replace(/\D/g, "");
  if (n.length === 9) n = "34" + n;
  return "https://wa.me/" + n + (text ? "?text=" + encodeURIComponent(text) : "");
};
export const norm = (t: string) => String(t).toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

export const ACTIVE: Status[] = ["pendiente", "esperando", "confirmada"];
export const STATUS_TXT: Record<Status, string> = {
  pendiente: "Petición nueva",
  esperando: "Esperando al paciente",
  confirmada: "Confirmada",
  rechazada: "Rechazada",
  cancelada: "Cancelada",
};
