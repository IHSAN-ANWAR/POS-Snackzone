"use client";

import { useState, useEffect, useCallback, useRef } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import {
  ShoppingBag, Clock, Bike, XCircle, ChefHat,
  ChevronDown, ChevronUp, LogOut, User,
  LayoutDashboard, ClipboardList, MapPin, Phone, Menu, X,
} from "lucide-react";
import { API_BASE } from "@/lib/api";
import { getUser, clearUser } from "@/lib/auth";

// ── Types ──────────────────────────────────────────────────
type OrderStatus = "pending" | "confirmed" | "dispatched" | "cancelled";
type ActiveTab   = "overview" | "orders" | "account";

interface OrderItem { id: number; title: string; price: number; qty: number; }
interface Order {
  id: number; name: string; phone: string; address: string;
  items: OrderItem[]; total: number; notes: string;
  status: OrderStatus; prep_time: string; created_at: string;
}
interface Stats {
  total: number; pending: number; confirmed: number;
  dispatched: number; cancelled: number; totalSpent: number;
}

// ── Status config ──────────────────────────────────────────
const STATUS: Record<OrderStatus, { label: string; color: string; bg: string; icon: React.ReactNode }> = {
  pending:    { label: "Pending",     color: "#e67e00", bg: "#fff7ed", icon: <Clock   className="w-3.5 h-3.5" /> },
  confirmed:  { label: "Preparation", color: "#2563eb", bg: "#eff6ff", icon: <ChefHat className="w-3.5 h-3.5" /> },
  dispatched: { label: "Delivery",    color: "#16a34a", bg: "#f0fdf4", icon: <Bike    className="w-3.5 h-3.5" /> },
  cancelled:  { label: "Cancelled",   color: "#888",    bg: "#f5f5f5", icon: <XCircle className="w-3.5 h-3.5" /> },
};

// ── Status badge ───────────────────────────────────────────
function StatusBadge({ status }: { status: OrderStatus }) {
  const s = STATUS[status] ?? STATUS.pending;
  return (
    <span style={{
      display: "inline-flex", alignItems: "center", gap: "5px",
      background: s.bg, color: s.color,
      fontSize: "0.68rem", fontWeight: 800, textTransform: "uppercase",
      letterSpacing: "0.06em", padding: "3px 10px",
      border: `1px solid ${s.color}33`,
    }}>
      {s.icon} {s.label}
    </span>
  );
}

// ── Order card ─────────────────────────────────────────────
function OrderCard({ order }: { order: Order }) {
  const [open, setOpen] = useState(false);
  const date = new Date(order.created_at).toLocaleDateString("en-PK", { day: "numeric", month: "short", year: "numeric" });
  const time = new Date(order.created_at).toLocaleTimeString("en-PK", { hour: "2-digit", minute: "2-digit" });

  return (
    <div className="profile-order-card">
      <div className="profile-order-head" onClick={() => setOpen(o => !o)}>
        <div className="profile-order-meta">
          <span className="profile-order-date">{date} · {time}</span>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: "10px", flexWrap: "wrap" }}>
          <StatusBadge status={order.status} />
          <span className="profile-order-total">Rs. {order.total.toLocaleString()}</span>
          {open ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
        </div>
      </div>

      <div className="profile-order-items-preview">
        {order.items.map((item, i) => (
          <span key={i} className="profile-order-item-chip">{item.title} × {item.qty}</span>
        ))}
        {/* Prep time badge — show when confirmed */}
        {order.status === "confirmed" && order.prep_time && (
          <span style={{
            display: "inline-flex", alignItems: "center", gap: "4px",
            background: "#eff6ff", color: "#2563eb",
            fontSize: "0.68rem", fontWeight: 800,
            padding: "3px 10px", border: "1px solid #2563eb33",
            textTransform: "uppercase", letterSpacing: "0.05em",
          }}>
            <Clock className="w-3 h-3" /> Ready in {order.prep_time}
          </span>
        )}
        {order.status === "dispatched" && (
          <span style={{
            display: "inline-flex", alignItems: "center", gap: "4px",
            background: "#f0fdf4", color: "#16a34a",
            fontSize: "0.68rem", fontWeight: 800,
            padding: "3px 10px", border: "1px solid #16a34a33",
            textTransform: "uppercase", letterSpacing: "0.05em",
          }}>
            <Bike className="w-3 h-3" /> On the way to you!
          </span>
        )}
      </div>

      {open && (
        <div className="profile-order-details">
          <div className="profile-order-detail-row">
            <span className="profile-detail-label">Address</span>
            <span className="profile-detail-val">{order.address}</span>
          </div>
          {order.notes && (
            <div className="profile-order-detail-row">
              <span className="profile-detail-label">Note</span>
              <span className="profile-detail-val">{order.notes}</span>
            </div>
          )}
          <div className="profile-order-detail-row">
            <span className="profile-detail-label">Items</span>
            <div style={{ display: "flex", flexDirection: "column", gap: "4px" }}>
              {order.items.map((item, i) => (
                <span key={i} className="profile-detail-val">
                  {item.title} × {item.qty}
                  <span style={{ color: "#888", marginLeft: "8px" }}>
                    Rs. {(item.price * item.qty).toLocaleString()}
                  </span>
                </span>
              ))}
            </div>
          </div>
          <div className="profile-order-detail-row" style={{ borderBottom: "none" }}>
            <span className="profile-detail-label">Total</span>
            <span style={{ fontWeight: 900, fontSize: "1rem" }}>
              Rs. {order.total.toLocaleString()}
            </span>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Sidebar content (shared between desktop & mobile) ──────
function SidebarContent({
  user, activeTab, stats, onTabChange, onClose,
}: {
  user: { name: string; email: string };
  activeTab: ActiveTab;
  stats: Stats | null;
  onTabChange: (t: ActiveTab) => void;
  onClose?: () => void;
}) {
  const navItems: { id: ActiveTab; label: string; icon: React.ReactNode }[] = [
    { id: "overview", label: "Overview",  icon: <LayoutDashboard className="w-4 h-4" /> },
    { id: "orders",   label: "My Orders", icon: <ClipboardList   className="w-4 h-4" /> },
    { id: "account",  label: "Account",   icon: <User            className="w-4 h-4" /> },
  ];

  const quickLinks = [
    { href: "/menu",     icon: <ShoppingBag className="w-3.5 h-3.5" />, label: "Order Food" },
    { href: "/location", icon: <MapPin      className="w-3.5 h-3.5" />, label: "Find Us" },
    { href: "/contact",  icon: <Phone       className="w-3.5 h-3.5" />, label: "Contact Us" },
  ];

  return (
    <>
      {onClose && (
        <div style={{ display: "flex", justifyContent: "flex-end", padding: "12px 16px", borderBottom: "1px solid rgba(0,0,0,0.07)" }}>
          <button onClick={onClose} style={{ background: "none", border: "none", cursor: "pointer" }}>
            <X className="w-5 h-5 text-black" />
          </button>
        </div>
      )}

      {/* Avatar */}
      <div style={{ padding: "1.4rem", borderBottom: "1px solid rgba(0,0,0,0.07)", textAlign: "center" }}>
        <div style={{ width: "56px", height: "56px", background: "#000", color: "#fff", fontSize: "1.5rem", fontWeight: 900, display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 10px" }}>
          {user.name.charAt(0).toUpperCase()}
        </div>
        <p style={{ fontWeight: 900, fontSize: "0.9rem", textTransform: "uppercase", letterSpacing: "-0.01em", margin: 0 }}>{user.name}</p>
        <p style={{ fontSize: "0.72rem", color: "#888", margin: "3px 0 0" }}>{user.email}</p>
      </div>

      {/* Nav */}
      <nav style={{ padding: "0.5rem 0" }}>
        {navItems.map(item => (
          <button key={item.id}
            onClick={() => { onTabChange(item.id); onClose?.(); }}
            style={{
              width: "100%", display: "flex", alignItems: "center", gap: "10px",
              padding: "0.75rem 1.2rem", border: "none", cursor: "pointer",
              background: activeTab === item.id ? "#000" : "transparent",
              color: activeTab === item.id ? "#fff" : "#555",
              fontSize: "0.78rem", fontWeight: 700, textTransform: "uppercase",
              letterSpacing: "0.05em", fontFamily: "Inter,sans-serif", textAlign: "left",
              transition: "background 0.15s, color 0.15s",
              borderLeft: activeTab === item.id ? "3px solid #000" : "3px solid transparent",
            }}>
            {item.icon} {item.label}
            {item.id === "orders" && stats && stats.pending > 0 && (
              <span style={{ marginLeft: "auto", background: "#e67e00", color: "#fff", fontSize: "0.6rem", fontWeight: 800, padding: "2px 7px" }}>
                {stats.pending}
              </span>
            )}
          </button>
        ))}
      </nav>

      {/* Quick links */}
      <div style={{ padding: "1rem 1.2rem", borderTop: "1px solid rgba(0,0,0,0.07)", marginTop: "0.5rem" }}>
        <p style={{ fontSize: "0.62rem", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", color: "#bbb", marginBottom: "0.6rem" }}>
          Quick Links
        </p>
        {quickLinks.map(l => (
          <Link key={l.href} href={l.href} style={{
            display: "flex", alignItems: "center", gap: "8px",
            padding: "0.5rem 0", fontSize: "0.75rem", fontWeight: 600,
            color: "#555", textDecoration: "none", borderBottom: "1px solid rgba(0,0,0,0.05)",
          }}
          onMouseEnter={e => (e.currentTarget.style.color = "#000")}
          onMouseLeave={e => (e.currentTarget.style.color = "#555")}
          >
            {l.icon} {l.label}
          </Link>
        ))}
      </div>
    </>
  );
}

// ── Main page ──────────────────────────────────────────────
export default function ProfilePage() {
  const router  = useRouter();
  const userRef = useRef(getUser());
  const user    = userRef.current;

  const [orders,    setOrders]    = useState<Order[]>([]);
  const [stats,     setStats]     = useState<Stats | null>(null);
  const [loading,   setLoading]   = useState(true);
  const [filter,    setFilter]    = useState<OrderStatus | "all">("all");
  const [activeTab, setActiveTab] = useState<ActiveTab>("overview");
  const [sideOpen,  setSideOpen]  = useState(false);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (!user) router.replace("/login");
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const fetchOrders = useCallback(async (silent = false) => {
    const u = userRef.current;
    if (!u) return;
    if (!silent) setLoading(true);
    try {
      const res  = await fetch(
        `${API_BASE}/get_user_orders.php?phone=${encodeURIComponent(u.phone)}&user_id=${u.id}`,
        { cache: "no-store" }
      );
      const data = await res.json();
      if (data.success) { setOrders(data.orders); setStats(data.stats); }
    } catch { /* silent */ }
    finally { if (!silent) setLoading(false); }
  }, []); // stable — uses ref, no deps needed

  useEffect(() => { fetchOrders(); }, [fetchOrders]);

  useEffect(() => {
    if (pollRef.current) clearInterval(pollRef.current);
    // Poll every 3s — always active so status updates feel instant
    pollRef.current = setInterval(() => fetchOrders(true), 3_000);
    return () => { if (pollRef.current) clearInterval(pollRef.current); };
  }, [fetchOrders]);

  const handleLogout = () => { clearUser(); router.push("/"); };

  if (!user) return null;

  const filtered = filter === "all" ? orders : orders.filter(o => o.status === filter);

  return (
    <div style={{ background: "#f5f5f5", minHeight: "80vh" }}>

      {/* Header */}
      <div style={{ background: "#000", padding: "28px 20px" }}>
        <div className="custom-container" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: "1rem" }}>
          <div style={{ display: "flex", alignItems: "center", gap: "14px" }}>
            <button onClick={() => setSideOpen(true)} className="md:hidden"
              style={{ background: "rgba(255,255,255,0.1)", border: "1px solid rgba(255,255,255,0.2)", color: "#fff", width: "38px", height: "38px", display: "flex", alignItems: "center", justifyContent: "center", cursor: "pointer" }}>
              <Menu className="w-4 h-4" />
            </button>
            <div>
              <p style={{ fontSize: "0.65rem", fontWeight: 800, letterSpacing: "0.15em", textTransform: "uppercase", color: "rgba(255,255,255,0.4)", marginBottom: "4px" }}>Snack Zone</p>
              <h1 style={{ fontWeight: 900, color: "#fff", fontSize: "clamp(1.6rem,4vw,2.5rem)", textTransform: "uppercase", letterSpacing: "-0.02em", lineHeight: 1 }}>My Profile</h1>
            </div>
          </div>
          <button onClick={handleLogout} style={{ display: "flex", alignItems: "center", gap: "8px", background: "rgba(255,255,255,0.1)", border: "1px solid rgba(255,255,255,0.2)", color: "#fff", padding: "10px 18px", fontFamily: "Inter,sans-serif", fontSize: "0.78rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.05em", cursor: "pointer" }}>
            <LogOut className="w-4 h-4" /> Logout
          </button>
        </div>
      </div>

      {/* Mobile overlay */}
      {sideOpen && <div onClick={() => setSideOpen(false)} style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.5)", zIndex: 40 }} />}

      {/* Mobile sidebar */}
      {sideOpen && (
        <aside style={{ position: "fixed", top: 0, left: 0, width: "260px", height: "100vh", background: "#fff", zIndex: 50, overflowY: "auto", boxShadow: "4px 0 24px rgba(0,0,0,0.15)" }}>
          <SidebarContent user={user} activeTab={activeTab} stats={stats} onTabChange={setActiveTab} onClose={() => setSideOpen(false)} />
        </aside>
      )}

      <div className="custom-container" style={{ padding: "24px 20px" }}>
        <div style={{ display: "flex", gap: "1.5rem", alignItems: "flex-start" }}>

          {/* Desktop sidebar */}
          <aside className="hidden md:block" style={{ width: "240px", flexShrink: 0, position: "sticky", top: "24px", background: "#fff", border: "1px solid rgba(0,0,0,0.08)", overflowY: "auto" }}>
            <SidebarContent user={user} activeTab={activeTab} stats={stats} onTabChange={setActiveTab} />
          </aside>

          {/* Main content */}
          <div style={{ flex: 1, minWidth: 0 }}>

            {/* Overview */}
            {activeTab === "overview" && (
              <div>
                <h2 style={{ fontWeight: 900, fontSize: "1rem", textTransform: "uppercase", letterSpacing: "0.06em", marginBottom: "1.2rem" }}>Overview</h2>

                {stats && (
                  <div style={{ background: "#fff", border: "1px solid rgba(0,0,0,0.08)", marginBottom: "1.2rem", overflow: "hidden" }}>
                    <div style={{ padding: "0.9rem 1.2rem", borderBottom: "2px solid #000", fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em" }}>Order Summary</div>
                    <div style={{ overflowX: "auto" }}>
                      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "0.82rem" }}>
                        <thead>
                          <tr style={{ background: "#f5f5f5" }}>
                            {["Name", "Total", "Pending", "Confirmed", "Dispatched", "Cancelled"].map(h => (
                              <th key={h} style={{ padding: "0.7rem 1rem", textAlign: "left", fontSize: "0.68rem", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.07em", color: "#888", borderBottom: "1px solid rgba(0,0,0,0.07)" }}>{h}</th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td style={{ padding: "0.9rem 1rem", fontWeight: 700 }}>{user.name}</td>
                            <td style={{ padding: "0.9rem 1rem", fontWeight: 900, fontSize: "1.1rem" }}>{stats.total}</td>
                            {[
                              { v: stats.pending,    bg: "#fff7ed", c: "#e67e00", b: "#e67e0033" },
                              { v: stats.confirmed,  bg: "#eff6ff", c: "#2563eb", b: "#2563eb33" },
                              { v: stats.dispatched, bg: "#f0fdf4", c: "#16a34a", b: "#16a34a33" },
                              { v: stats.cancelled,  bg: "#f5f5f5", c: "#888",    b: "#88888833" },
                            ].map((s, i) => (
                              <td key={i} style={{ padding: "0.9rem 1rem" }}>
                                <span style={{ background: s.bg, color: s.c, fontWeight: 800, fontSize: "0.75rem", padding: "3px 10px", border: `1px solid ${s.b}` }}>{s.v}</span>
                              </td>
                            ))}
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}

                <div style={{ background: "#fff", border: "1px solid rgba(0,0,0,0.08)", overflow: "hidden" }}>
                  <div style={{ padding: "0.9rem 1.2rem", borderBottom: "2px solid #000", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
                    <span style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em" }}>Recent Orders</span>
                    <button onClick={() => setActiveTab("orders")} style={{ background: "none", border: "none", cursor: "pointer", fontSize: "0.72rem", fontWeight: 700, textTransform: "uppercase", color: "#888" }}>View All →</button>
                  </div>
                  <div style={{ padding: "0.8rem" }}>
                    {loading ? (
                      <p style={{ textAlign: "center", color: "#aaa", padding: "1.5rem", fontSize: "0.82rem" }}>Loading…</p>
                    ) : orders.length === 0 ? (
                      <div style={{ textAlign: "center", padding: "2rem", color: "#bbb" }}>
                        <ShoppingBag className="w-8 h-8 mx-auto mb-2" style={{ color: "#e0e0e0" }} />
                        <p style={{ fontSize: "0.82rem" }}>No orders yet.</p>
                        <Link href="/menu" className="btn-black inline-flex items-center gap-2 mt-3" style={{ fontSize: "0.75rem" }}>Order Now</Link>
                      </div>
                    ) : (
                      orders.slice(0, 3).map(order => <OrderCard key={order.id} order={order} />)
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* My Orders */}
            {activeTab === "orders" && (
              <div>
                <h2 style={{ fontWeight: 900, fontSize: "1rem", textTransform: "uppercase", letterSpacing: "0.06em", marginBottom: "1.2rem" }}>My Orders</h2>

                {!loading && orders.length > 0 && (
                  <div className="profile-filter-tabs" style={{ marginBottom: "1rem" }}>
                    {(["all", "pending", "confirmed", "dispatched", "cancelled"] as const).map(f => (
                      <button key={f} onClick={() => setFilter(f)} className={`profile-filter-tab ${filter === f ? "active" : ""}`}>
                        {f === "all" ? "All" : STATUS[f].label}
                        <span className="profile-filter-count">
                          {f === "all" ? orders.length : orders.filter(o => o.status === f).length}
                        </span>
                      </button>
                    ))}
                  </div>
                )}

                {loading ? (
                  <div style={{ textAlign: "center", padding: "3rem", color: "#aaa" }}>
                    <Clock className="w-7 h-7 mx-auto mb-3 animate-spin" style={{ color: "#ddd" }} />
                    <p style={{ fontSize: "0.82rem" }}>Loading orders…</p>
                  </div>
                ) : filtered.length === 0 ? (
                  <div style={{ textAlign: "center", padding: "3rem", color: "#bbb" }}>
                    <ShoppingBag className="w-10 h-10 mx-auto mb-3" style={{ color: "#ddd" }} />
                    <p style={{ fontWeight: 700, fontSize: "0.9rem" }}>
                      {orders.length === 0 ? "No orders yet." : "No orders in this category."}
                    </p>
                    {orders.length === 0 && (
                      <Link href="/menu" className="btn-black inline-flex items-center gap-2 mt-4" style={{ fontSize: "0.8rem" }}>Order Now</Link>
                    )}
                  </div>
                ) : (
                  <div className="profile-orders-list">
                    {filtered.map(order => <OrderCard key={order.id} order={order} />)}
                  </div>
                )}
              </div>
            )}

            {/* Account */}
            {activeTab === "account" && (
              <div>
                <h2 style={{ fontWeight: 900, fontSize: "1rem", textTransform: "uppercase", letterSpacing: "0.06em", marginBottom: "1.2rem" }}>Account Info</h2>
                <div style={{ background: "#fff", border: "1px solid rgba(0,0,0,0.08)", padding: "1.4rem" }}>
                  {[
                    { label: "Full Name",      value: user.name },
                    { label: "Email Address",  value: user.email },
                    { label: "Phone Number",   value: user.phone },
                    { label: "Account Status", value: "Verified ✓" },
                  ].map((row, i, arr) => (
                    <div key={i} style={{ display: "flex", justifyContent: "space-between", padding: "0.8rem 0", borderBottom: i < arr.length - 1 ? "1px solid rgba(0,0,0,0.06)" : "none", flexWrap: "wrap", gap: "0.5rem" }}>
                      <span style={{ fontSize: "0.72rem", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.07em", color: "#aaa" }}>{row.label}</span>
                      <span style={{ fontSize: "0.85rem", fontWeight: 600, color: "#000" }}>{row.value}</span>
                    </div>
                  ))}
                </div>
                <button onClick={handleLogout} style={{ marginTop: "1.2rem", display: "flex", alignItems: "center", gap: "8px", background: "#000", color: "#fff", border: "none", padding: "12px 24px", fontFamily: "Inter,sans-serif", fontSize: "0.78rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.05em", cursor: "pointer" }}>
                  <LogOut className="w-4 h-4" /> Logout
                </button>
              </div>
            )}

          </div>
        </div>
      </div>
    </div>
  );
}
