export interface AuthUser {
  id: number;
  name: string;
  email: string;
  phone: string;
}

const KEY = "sz_user";

export function getUser(): AuthUser | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = localStorage.getItem(KEY);
    return raw ? (JSON.parse(raw) as AuthUser) : null;
  } catch {
    return null;
  }
}

export function setUser(user: AuthUser): void {
  localStorage.setItem(KEY, JSON.stringify(user));
  // Also save phone for orders page auto-load
  localStorage.setItem("sz_phone", user.phone);
  localStorage.setItem("sz_name",  user.name);
}

export function clearUser(): void {
  localStorage.removeItem(KEY);
  localStorage.removeItem("sz_phone");
  localStorage.removeItem("sz_name");
}

export function isLoggedIn(): boolean {
  return getUser() !== null;
}
