"use client";

import { useState, useEffect, useCallback, useRef } from "react";
import {
  Package, Clock, ChefHat, Bike, XCircle,
  ShoppingBag, ChevronDown, ChevronUp, LogIn,
} from "lucide-react";
import Link from "next/link";
import { API_BASE } from "@/lib/api";
import { getUser } from "@/lib/auth";

type OrderStatus = "pending" | "confirmed" | "dispatched" | "cancelled";

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

const STATUS: Record<OrderStatus, { label: string; color: string; bg: string; icon: React.ReactNode }> = {
  pending:    { label: "Pending",     color: "#e67e00", bg: "#fff7ed", icon: <Clock   className="w-3.5 h-3.5" /> },
  confirmed:  { label: "Preparation", color: "#2563eb", bg: "#eff6ff", icon: <ChefHat className="w-3.5 h-3.5" /> },
  dispatched: { label: "Delivery",    color: "#16a34a", bg: "#f0fdf4", icon: <Bike    className="w-3.5 h-3.5" /> },
  cancelled:  { label: "Cancelled",   color: "#888",    bg: "#f5f5f5", icon: <XCircle className="w-3.5 h-3.5" /> },
};

function StatusBadge({ status }: { status: OrderStatus }) {
  const s = STATUS[status] ?? STATUS.pending;
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: "5px",
      background: s.bg, color: s.color, fontSize: "0.68rem", fontWeight: 800,
      textTransform: "uppercase", letterSpacing: "0.06em", padding: "3px 10px",
      border: `1px solid ${s.color}33` }}>
      {s.icon} {s.label}
    </span>
  );
}

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
                  <span style={{ color: "#888", marginLeft: "8px" }}>Rs. {(item.price * item.qty).toLocaleString()}</span>
                </span>
              ))}
            </div>
          </div>
          <div className="profile-order-detail-row" style={{ borderBottom: "none" }}>
            <span className="profile-detail-label">Total</span>
            <span style={{ fontWeight: 900, fontSize: "1rem" }}>Rs. {order.total.toLocaleString()}</span>
          </div>
        </div>
      )}
    </div>
  );
}

export default function OrdersPage() {
  const userRef = useRef(getUser());
  const user    = userRef.current;

  const [orders,  setOrders]  = useState<Order[]>([]);
  const [stats,   setStats]   = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);
  const [filter,  setFilter]  = useState<OrderStatus | "all">("all");
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

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
  }, []);

  useEffect(() => { fetchOrders(); }, [fetchOrders]);

  // Poll every 5s; also refetch immediately when user returns to the tab
  useEffect(() => {
    if (pollRef.current) clearInterval(pollRef.current);
    pollRef.current = setInterval(() => fetchOrders(true), 5_000);

    const onVisible = () => { if (document.visibilityState === "visible") fetchOrders(true); };
    document.addEventListener("visibilitychange", onVisible);

    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, [fetchOrders]);

  const filtered = filter === "all" ? orders : orders.filter(o => o.status === filter);

  // Not logged in — show login prompt
  if (!user) {
    return (
      <div style={{ background: "#f5f5f5", minHeight: "80vh" }}>
        <div style={{ background: "#000", padding: "40px 20px" }}>
          <div className="custom-container">
            <p style={{ fontSize: "0.7rem", fontWeight: 800, letterSpacing: "0.15em", textTransform: "uppercase", color: "rgba(255,255,255,0.4)", marginBottom: "6px" }}>Snack Zone</p>
            <h1 style={{ fontWeight: 900, color: "#fff", fontSize: "clamp(2rem,6vw,3.5rem)", textTransform: "uppercase", letterSpacing: "-0.02em", lineHeight: 1 }}>My Orders</h1>
          </div>
        </div>
        <div className="custom-container" style={{ padding: "60px 20px", textAlign: "center" }}>
          <ShoppingBag className="w-14 h-14 mx-auto mb-4" style={{ color: "#ddd" }} />
          <h2 style={{ fontWeight: 900, fontSize: "1.2rem", textTransform: "uppercase", letterSpacing: "-0.01em", marginBottom: "10px" }}>
            Login to View Your Orders
          </h2>
          <p style={{ fontSize: "0.85rem", color: "#888", marginBottom: "24px" }}>
            Login or register to track your orders in real-time.
          </p>
          <div style={{ display: "flex", gap: "12px", justifyContent: "center", flexWrap: "wrap" }}>
            <Link href="/login" className="btn-black" style={{ display: "inline-flex", alignItems: "center", gap: "6px" }}>
              <LogIn className="w-4 h-4" /> Login
            </Link>
            <Link href="/register" className="btn-white" style={{ display: "inline-flex", alignItems: "center", gap: "6px" }}>
              Register
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div style={{ background: "#f5f5f5", minHeight: "80vh" }}>

      {/* Header */}
      <div style={{ background: "#000", padding: "40px 20px" }}>
        <div className="custom-container" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: "1rem" }}>
          <div>
            <p style={{ fontSize: "0.7rem", fontWeight: 800, letterSpacing: "0.15em", textTransform: "uppercase", color: "rgba(255,255,255,0.4)", marginBottom: "6px" }}>Snack Zone</p>
            <h1 style={{ fontWeight: 900, color: "#fff", fontSize: "clamp(2rem,6vw,3.5rem)", textTransform: "uppercase", letterSpacing: "-0.02em", lineHeight: 1 }}>My Orders</h1>
          </div>
          <Link href="/profile" style={{ display: "flex", alignItems: "center", gap: "8px", background: "rgba(255,255,255,0.1)", border: "1px solid rgba(255,255,255,0.2)", color: "#fff", padding: "10px 18px", fontFamily: "Inter,sans-serif", fontSize: "0.78rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.05em", textDecoration: "none" }}>
            ← My Profile
          </Link>
        </div>
      </div>

      <div className="custom-container" style={{ padding: "32px 20px" }}>

        {/* Filter tabs */}
        {!loading && orders.length > 0 && (
          <div className="profile-filter-tabs" style={{ marginBottom: "1rem" }}>
            {(["all", "pending", "confirmed", "dispatched", "cancelled"] as const).map(f => (
              <button key={f} onClick={() => setFilter(f)}
                className={`profile-filter-tab ${filter === f ? "active" : ""}`}>
                {f === "all" ? "All" : STATUS[f].label}
                <span className="profile-filter-count">
                  {f === "all" ? orders.length : orders.filter(o => o.status === f).length}
                </span>
              </button>
            ))}
          </div>
        )}

        {/* Orders */}
        {loading ? (
          <div style={{ textAlign: "center", padding: "3rem", color: "#aaa" }}>
            <Clock className="w-8 h-8 mx-auto mb-3 animate-spin" style={{ color: "#ddd" }} />
            <p style={{ fontSize: "0.85rem" }}>Loading your orders…</p>
          </div>
        ) : filtered.length === 0 ? (
          <div style={{ textAlign: "center", padding: "3rem", color: "#bbb" }}>
            <Package className="w-10 h-10 mx-auto mb-3" style={{ color: "#ddd" }} />
            <p style={{ fontWeight: 700, fontSize: "0.9rem" }}>
              {orders.length === 0 ? "No orders yet." : "No orders in this category."}
            </p>
            {orders.length === 0 && (
              <Link href="/menu" className="btn-black inline-flex items-center gap-2 mt-4" style={{ fontSize: "0.8rem" }}>
                Order Now
              </Link>
            )}
          </div>
        ) : (
          <div className="profile-orders-list">
            {filtered.map(order => <OrderCard key={order.id} order={order} />)}
          </div>
        )}
      </div>
    </div>
  );
}
