// ── Backend API base URL ──────────────────────────────────
// Local  : http://localhost/snackzone/Admin
// Live   : https://yourdomain.com/Admin
//
// Set NEXT_PUBLIC_API_URL in .env.local to override:
//   NEXT_PUBLIC_API_URL=http://localhost/snackzone/Admin

export const API_BASE =
  process.env.NEXT_PUBLIC_API_URL ?? "https://snackzone.food/Admin";

// ── Helper: POST form data to a PHP endpoint ─────────────
export async function postForm(
  endpoint: string,
  data: Record<string, string | number>
): Promise<{ success: boolean; message?: string }> {
  const body = new URLSearchParams();
  Object.entries(data).forEach(([k, v]) => body.append(k, String(v)));

  const res = await fetch(`${API_BASE}/${endpoint}`, {
    method: "POST",
    body,
  });

  if (!res.ok) {
    return { success: false, message: `Server error: ${res.status}` };
  }

  try {
    return await res.json();
  } catch {
    return { success: false, message: "Invalid response from server" };
  }
}

// ── Helper: GET JSON from a PHP endpoint ─────────────────
export async function getJSON<T = unknown>(
  endpoint: string,
  params?: Record<string, string>
): Promise<T> {
  const url = new URL(`${API_BASE}/${endpoint}`);
  if (params) {
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  }
  const res = await fetch(url.toString(), { cache: "no-store" });
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}
