const raw = (import.meta.env.BASE_URL as string | undefined) ?? "/";

export const basePath = raw.length > 1 ? raw.replace(/\/+$/, "") : "";

export function withBase(path: string): string {
  if (/^[a-z][a-z0-9+.-]*:/i.test(path)) return path;
  const suffix = path.startsWith("/") ? path : `/${path}`;
  return `${basePath}${suffix}`;
}

export function stripBase(pathname: string): string {
  if (basePath && pathname.startsWith(basePath)) {
    const rest = pathname.slice(basePath.length);
    return rest === "" || rest.startsWith("/") ? rest || "/" : pathname;
  }
  return pathname;
}

export function normalizePath(path: string): string {
  const clean = path.split("#")[0].split("?")[0];
  return clean.length > 1 ? clean.replace(/\/+$/, "") : clean;
}
