"use client";

import Image from "next/image";
import { useState, useEffect, useCallback, useRef, useMemo, memo } from "react";
import { motion } from "framer-motion";
import { ShoppingCart, Plus, X, Minus, Trash2, CheckCircle, Loader2, ChefHat, Bike, XCircle, Clock, Search, ChevronRight } from "lucide-react";
import { API_BASE } from "@/lib/api";

interface MenuItem {
  id: number;
  title: string;
  category: string;
  description: string;
  price: number;
  discount_percent: number;
  image_path: string;
  tag: string;
  featured: boolean;
  addons: { id: number; name: string; price: number }[];
}

interface CartItem extends MenuItem {
  qty: number;
  selectedAddons: { id: number; name: string; price: number }[];
}

interface CategoryGroup {
  id: number;
  name: string;
  slug: string;
  banner_image: string;
  items: MenuItem[];
}

function imgSrc(image_path: string): string {
  if (!image_path) return "";
  return API_BASE.replace(/\/Admin\/?$/, "") + "/" + image_path;
}

// ── Cart Drawer (same as menu page) ──────────────────────
type OrderStatus = "pending" | "confirmed" | "dispatched" | "cancelled";
type DrawerView  = "cart" | "checkout" | "success" | "login";

interface CartActions {
  cart: CartItem[];
  addToCart: (item: MenuItem, addons?: { id: number; name: string; price: number }[]) => void;
  removeFromCart: (id: number) => void;
  updateQty: (id: number, qty: number) => void;
  clearCart: () => void;
  totalItems: number;
  totalPrice: number;
}

const STATUS_CONFIG: Record<OrderStatus, { icon: React.ReactNode; title: string; sub: string; note: string; color: string }> = {
  pending:    { icon: <Clock    className="w-7 h-7" />, title: "Order Received!",       sub: "We've received your order and will confirm it shortly.", note: "Keep your phone nearby — our team will confirm shortly.", color: "#e67e00" },
  confirmed:  { icon: <ChefHat className="w-7 h-7" />, title: "Preparation Started!",  sub: "Your order is being freshly prepared.",                   note: "Almost ready — it'll be on its way soon!",                color: "#2563eb" },
  dispatched: { icon: <Bike    className="w-7 h-7" />, title: "Out for Delivery!",     sub: "Your order is on the way.",                               note: "Expected delivery in 30–45 minutes. Get ready!",           color: "#16a34a" },
  cancelled:  { icon: <XCircle className="w-7 h-7" />, title: "Order Cancelled",       sub: "Your order was cancelled.",                               note: "Please contact us if you have any questions.",             color: "#dc2626" },
};

function CartDrawer({ cart, addToCart, removeFromCart, updateQty, clearCart, totalItems, totalPrice, onClose }:
  CartActions & { onClose: () => void }) {

  const [view, setView]         = useState<DrawerView>("cart");
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [form, setForm]         = useState({ name: "", phone: "", address: "", note: "" });
  const [errors, setErrors]     = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [orderId, setOrderId]   = useState<number | null>(null);
  const [orderStatus, setOrderStatus] = useState<OrderStatus>("pending");
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    try {
      const raw = localStorage.getItem("sz_user");
      if (raw) {
        const u = JSON.parse(raw);
        setIsLoggedIn(!!u?.id);
        setForm(f => ({ ...f, name: f.name || u.name || "", phone: f.phone || u.phone || "" }));
      }
      
      // Load delivery choice and pre-fill address
      const deliveryRaw = localStorage.getItem("sz_delivery_choice");
      if (deliveryRaw) {
        const choice = JSON.parse(deliveryRaw);
        if (choice.type === "delivery" && choice.city && choice.address) {
          setForm(f => ({ ...f, address: `${choice.address}, ${choice.city}` }));
        }
      }
    } catch { /* ignore */ }
  }, []);

  const validate = () => {
    const e: Record<string, string> = {};
    if (!form.name.trim())    e.name    = "Name is required";
    if (!form.phone.trim())   e.phone   = "Phone is required";
    if (!form.address.trim()) e.address = "Address is required";
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;
    setSubmitting(true);
    const loggedUser = typeof window !== "undefined"
      ? (() => { try { const r = localStorage.getItem("sz_user"); return r ? JSON.parse(r) : null; } catch { return null; } })()
      : null;
    if (!loggedUser?.id) {
      localStorage.setItem("sz_cart_pending", JSON.stringify(cart));
      window.location.href = "/login/";
      return;
    }
    const body = new URLSearchParams({
      name: form.name, phone: form.phone, address: form.address, notes: form.note,
      items: JSON.stringify(cart.map(i => ({ id: i.id, title: i.title, price: i.price, qty: i.qty }))),
      total: String(totalPrice),
      ...(loggedUser?.id ? { user_id: String(loggedUser.id) } : {}),
    });
    try {
      const res  = await fetch(`${API_BASE}/save_order.php`, { method: "POST", body });
      const data = await res.json();
      if (data.success) { setOrderId(data.order_id); setView("success"); clearCart(); }
      else setErrors({ submit: data.message || "Could not place order. Try again." });
    } catch { setErrors({ submit: "Network error. Please try again." }); }
    finally  { setSubmitting(false); }
  };

  useEffect(() => {
    if (view !== "success" || !orderId) return;
    const poll = async () => {
      try {
        const res  = await fetch(`${API_BASE}/get_order_status.php?id=${orderId}`, { cache: "no-store" });
        const data = await res.json();
        if (data.success && data.status) {
          setOrderStatus(data.status as OrderStatus);
          if (data.status === "dispatched" || data.status === "cancelled") { if (pollRef.current) clearInterval(pollRef.current); }
        }
      } catch { /* silent */ }
    };
    poll();
    pollRef.current = setInterval(poll, 10_000);
    return () => { if (pollRef.current) clearInterval(pollRef.current); };
  }, [view, orderId]);

  const inputCls = (f: string) => `cart-input${errors[f] ? " cart-input-err" : ""}`;

  return (
    <>
      <div className="cart-overlay" onClick={onClose} aria-hidden="true" />
      <div className="cart-drawer" role="dialog" aria-label="Cart">

        {view === "cart" && (
          <>
            <div className="cart-drawer-head">
              <div><h2 className="cart-drawer-title">Your Cart</h2><p className="cart-drawer-sub">{totalItems} item{totalItems !== 1 ? "s" : ""}</p></div>
              <button className="cart-close-btn" onClick={onClose}><X className="w-5 h-5" /></button>
            </div>
            {cart.length === 0 ? (
              <div className="cart-empty"><ShoppingCart className="cart-empty-icon" /><p>Your cart is empty</p><span>Add items from the menu</span></div>
            ) : (
              <>
                <div className="cart-items">
                  {cart.map(item => {
                    const itemAddonsTotal = (item.selectedAddons || []).reduce((s, a) => s + a.price, 0);
                    const itemTotal = (item.price + itemAddonsTotal) * item.qty;
                    return (
                      <div key={item.id} className="cart-item">
                        <div className="cart-item-info">
                          <p className="cart-item-name">{item.title}</p>
                          {item.selectedAddons && item.selectedAddons.length > 0 && (
                            <p style={{ fontSize: "0.7rem", color: "#888", margin: "2px 0 0", lineHeight: 1.3 }}>
                              + {item.selectedAddons.map(a => a.name).join(", ")}
                            </p>
                          )}
                          <p className="cart-item-price">Rs. {item.price.toLocaleString()}{itemAddonsTotal > 0 && <span style={{ fontSize: "0.75rem", color: "#888" }}> + {itemAddonsTotal}</span>}</p>
                        </div>
                        <div className="cart-item-controls">
                          <button className="cart-qty-btn" onClick={() => updateQty(item.id, item.qty - 1)}><Minus className="w-3 h-3" /></button>
                          <span className="cart-qty-num">{item.qty}</span>
                          <button className="cart-qty-btn" onClick={() => updateQty(item.id, item.qty + 1)}><Plus className="w-3 h-3" /></button>
                          <button className="cart-del-btn" onClick={() => removeFromCart(item.id)}><Trash2 className="w-3.5 h-3.5" /></button>
                        </div>
                      </div>
                    );
                  })}
                </div>
                <div className="cart-footer">
                  <div className="cart-total-row"><span className="cart-total-label">Total</span><span className="cart-total-price">Rs. {totalPrice.toLocaleString()}</span></div>
                  <button className="cart-checkout-btn" onClick={() => setView("checkout")}>
                    Proceed to Checkout
                  </button>
                  <button className="cart-clear-btn" onClick={clearCart}>Clear Cart</button>
                </div>
              </>
            )}
          </>
        )}

        {view === "checkout" && (
          <>
            <div className="cart-drawer-head">
              <div><h2 className="cart-drawer-title">Place Order</h2><p className="cart-drawer-sub">Rs. {totalPrice.toLocaleString()} · {totalItems} item{totalItems !== 1 ? "s" : ""}</p></div>
              <button className="cart-close-btn" onClick={() => setView("cart")}><X className="w-5 h-5" /></button>
            </div>
            <form onSubmit={handleSubmit} className="cart-form" noValidate>
              <div className="cart-form-group"><label className="cart-label">Full Name *</label><input className={inputCls("name")} value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="Your name" />{errors.name && <span className="cart-err">{errors.name}</span>}</div>
              <div className="cart-form-group"><label className="cart-label">Phone Number *</label><input className={inputCls("phone")} value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} placeholder="03XX XXXXXXX" type="tel" />{errors.phone && <span className="cart-err">{errors.phone}</span>}</div>
              <div className="cart-form-group"><label className="cart-label">Delivery Address *</label><textarea className={inputCls("address")} value={form.address} onChange={e => setForm(f => ({ ...f, address: e.target.value }))} placeholder="House No, Street, Area, City" rows={3} />{errors.address && <span className="cart-err">{errors.address}</span>}</div>
              <div className="cart-form-group"><label className="cart-label">Note <span style={{ color: "#aaa", fontWeight: 400 }}>(optional)</span></label><input className="cart-input" value={form.note} onChange={e => setForm(f => ({ ...f, note: e.target.value }))} placeholder="e.g. Extra sauce, ring bell" /></div>
              <div className="cart-summary">
                {cart.map(i => {
                  const addonsTotal = (i.selectedAddons || []).reduce((s, a) => s + a.price, 0);
                  const lineTotal = (i.price + addonsTotal) * i.qty;
                  return (
                    <div key={i.id} className="cart-summary-row">
                      <span>{i.title} × {i.qty}{addonsTotal > 0 && <span style={{ fontSize: "0.75rem", color: "#888" }}> (+add-ons)</span>}</span>
                      <span>Rs. {lineTotal.toLocaleString()}</span>
                    </div>
                  );
                })}
                <div className="cart-summary-total"><span>Total</span><span>Rs. {totalPrice.toLocaleString()}</span></div>
              </div>
              {errors.submit && <p className="cart-err" style={{ marginBottom: "0.8rem" }}>{errors.submit}</p>}
              <button type="submit" className="cart-checkout-btn" disabled={submitting}>
                {submitting ? <><Loader2 className="w-4 h-4 animate-spin" /> Placing Order…</> : <><CheckCircle className="w-4 h-4" /> Confirm Order</>}
              </button>
            </form>
          </>
        )}

        {view === "login" && (
          <>
            <div className="cart-drawer-head">
              <div>
                <h2 className="cart-drawer-title">Login Required</h2>
                <p className="cart-drawer-sub">Sign in to place your order</p>
              </div>
              <button className="cart-close-btn" onClick={() => setView("cart")} aria-label="Back">
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="cart-success" style={{ justifyContent: "flex-start", paddingTop: "3rem" }}>
              <div className="cart-success-icon" style={{ background: "#000" }}>
                <ShoppingCart className="w-8 h-8" />
              </div>
              <h2 className="cart-success-title" style={{ fontSize: "1.1rem" }}>Almost There!</h2>
              <p className="cart-success-sub">
                Your cart has {totalItems} item{totalItems !== 1 ? "s" : ""} — Rs. {totalPrice.toLocaleString()}
              </p>
              <p className="cart-success-note">
                Login or create an account to complete your order. Your cart will be saved.
              </p>
              <a href="/login/"
                onClick={() => localStorage.setItem("sz_cart_pending", JSON.stringify(cart))}
                className="cart-checkout-btn"
                style={{ width: "100%", textDecoration: "none", justifyContent: "center", marginTop: "0.5rem" }}>
                Login to Continue
              </a>
              <a href="/register/"
                onClick={() => localStorage.setItem("sz_cart_pending", JSON.stringify(cart))}
                style={{ width: "100%", textAlign: "center", padding: "0.75rem", border: "1px solid rgba(0,0,0,0.15)", fontSize: "0.75rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.08em", color: "#000", textDecoration: "none", display: "block" }}>
                Create Account
              </a>
              <button onClick={() => setView("cart")} className="cart-clear-btn" style={{ marginTop: "0.5rem" }}>
                ← Back to Cart
              </button>
            </div>
          </>
        )}

        {view === "success" && (() => {
          const cfg    = STATUS_CONFIG[orderStatus] ?? STATUS_CONFIG.pending;
          const isDone = orderStatus === "dispatched" || orderStatus === "cancelled";
          const steps  = ["pending", "confirmed", "dispatched"] as OrderStatus[];
          const curIdx = steps.indexOf(orderStatus === "cancelled" ? "pending" : orderStatus);
          return (
            <div className="cart-success">
              <div className="cart-success-icon" style={{ background: cfg.color, color: "#fff" }}>{cfg.icon}</div>
              <h2 className="cart-success-title">{cfg.title}</h2>
              <p className="cart-success-sub">{cfg.sub}</p>
              <p className="cart-success-note">{cfg.note}</p>
              <div className="cart-status-track">
                {steps.map((s, i) => (
                  <div key={s} className="cart-status-step">
                    <div className={`cart-status-dot ${i <= curIdx && orderStatus !== "cancelled" ? "active" : ""}`} style={i <= curIdx && orderStatus !== "cancelled" ? { background: cfg.color } : {}}>{i + 1}</div>
                    <span className={`cart-status-label ${i <= curIdx && orderStatus !== "cancelled" ? "active" : ""}`}>{i === 0 ? "Received" : i === 1 ? "Preparation" : "Delivery"}</span>
                    {i < 2 && <div className={`cart-status-line ${i < curIdx && orderStatus !== "cancelled" ? "active" : ""}`} style={i < curIdx && orderStatus !== "cancelled" ? { background: cfg.color } : {}} />}
                  </div>
                ))}
              </div>
              {!isDone && <p className="cart-polling-note"><span className="cart-polling-dot" /> Tracking your order live...</p>}
              <button className="cart-checkout-btn" style={{ marginTop: "1rem" }} onClick={onClose}>Continue Browsing</button>
            </div>
          );
        })()}
      </div>
    </>
  );
}

// ── Delivery/Pickup Modal ─────────────────────────────────
function DeliveryModal({ isOpen, onClose, pickupLocation }: {
  isOpen: boolean;
  onClose: (type: "pickup" | "delivery", city?: string, address?: string) => void;
  pickupLocation: string;
}) {
  const [activeTab, setActiveTab] = useState<"pickup" | "delivery">("pickup");
  const [city, setCity] = useState("");
  const [address, setAddress] = useState("");

  if (!isOpen) return null;

  const handleConfirm = () => {
    if (activeTab === "delivery" && (!city.trim() || !address.trim())) {
      alert("Please enter both city and delivery address");
      return;
    }
    onClose(activeTab, city, address);
  };

  return (
    <>
      {/* Overlay */}
      <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.75)", zIndex: 9999, animation: "fadeIn 0.2s ease" }} />

      {/* Modal */}
      <div style={{
        position: "fixed", top: "50%", left: "50%", transform: "translate(-50%, -50%)",
        zIndex: 10000, background: "#fff", borderRadius: "20px", overflow: "hidden",
        width: "94%", maxWidth: "580px", boxShadow: "0 30px 70px rgba(0,0,0,0.5)",
        animation: "modalIn 0.22s ease",
      }}>
        {/* Close button */}
        <button
          onClick={() => onClose("pickup")}
          aria-label="Close"
          style={{
            position: "absolute",
            top: "16px",
            right: "16px",
            zIndex: 10,
            width: "32px",
            height: "32px",
            borderRadius: "50%",
            background: "rgba(255,255,255,0.2)",
            border: "none",
            cursor: "pointer",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            transition: "background 0.2s",
          }}
          onMouseEnter={e => (e.currentTarget.style.background = "rgba(255,255,255,0.3)")}
          onMouseLeave={e => (e.currentTarget.style.background = "rgba(255,255,255,0.2)")}
        >
          <X className="w-4 h-4" style={{ color: "#fff" }} />
        </button>

        {/* Header */}
        <div style={{ background: "#000", color: "#fff", padding: "28px 32px", textAlign: "center" }}>
          <h2 style={{ fontWeight: 900, fontSize: "1.5rem", margin: 0, textTransform: "uppercase", letterSpacing: "-0.02em" }}>
            Choose Order Type
          </h2>
          <p style={{ fontSize: "0.88rem", margin: "8px 0 0", opacity: 0.75 }}>How would you like to receive your order?</p>
        </div>

        {/* Tabs */}
        <div style={{ display: "flex", borderBottom: "2px solid #f0f0f0" }}>
          <button onClick={() => setActiveTab("pickup")} style={{
            flex: 1, padding: "18px", background: "transparent", border: "none",
            borderBottom: activeTab === "pickup" ? "3px solid #000" : "3px solid transparent",
            fontWeight: 700, fontSize: "0.9rem", textTransform: "uppercase", letterSpacing: "0.06em",
            color: activeTab === "pickup" ? "#000" : "#888", cursor: "pointer", fontFamily: "inherit",
            transition: "all 0.2s", display: "flex", alignItems: "center", justifyContent: "center", gap: "8px",
          }}>
            <ShoppingCart className="w-4 h-4" /> Pickup
          </button>
          <button onClick={() => setActiveTab("delivery")} style={{
            flex: 1, padding: "18px", background: "transparent", border: "none",
            borderBottom: activeTab === "delivery" ? "3px solid #000" : "3px solid transparent",
            fontWeight: 700, fontSize: "0.9rem", textTransform: "uppercase", letterSpacing: "0.06em",
            color: activeTab === "delivery" ? "#000" : "#888", cursor: "pointer", fontFamily: "inherit",
            transition: "all 0.2s", display: "flex", alignItems: "center", justifyContent: "center", gap: "8px",
          }}>
            <Bike className="w-4 h-4" /> Delivery
          </button>
        </div>

        {/* Content */}
        <div style={{ padding: "32px" }}>
          {activeTab === "pickup" ? (
            <div>
              <div style={{ background: "#f9f9f9", border: "1px solid rgba(0,0,0,0.08)", borderRadius: "12px", padding: "20px", marginBottom: "24px" }}>
                <p style={{ fontSize: "0.75rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.08em", color: "#888", margin: "0 0 10px", display: "flex", alignItems: "center", gap: "6px" }}>
                  <span style={{ width: "6px", height: "6px", borderRadius: "50%", background: "#16a34a" }}></span>
                  Pickup Location
                </p>
                <p style={{ fontSize: "1rem", fontWeight: 600, color: "#000", margin: 0, lineHeight: 1.6 }}>
                  {pickupLocation}
                </p>
              </div>
              <p style={{ fontSize: "0.88rem", color: "#666", lineHeight: 1.7, margin: 0 }}>
                Place your order and pick it up from our location. We'll notify you when it's ready!
              </p>
            </div>
          ) : (
            <div>
              <div style={{ marginBottom: "20px" }}>
                <label style={{ display: "block", fontSize: "0.75rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.08em", color: "#000", marginBottom: "10px" }}>
                  Your City *
                </label>
                <input
                  type="text"
                  value={city}
                  onChange={e => setCity(e.target.value)}
                  placeholder="City name"
                  style={{ width: "100%", padding: "14px 16px", border: "1.5px solid rgba(0,0,0,0.15)", borderRadius: "10px", fontSize: "0.95rem", fontFamily: "inherit", outline: "none", transition: "border 0.2s" }}
                  onFocus={e => e.target.style.borderColor = "#000"}
                  onBlur={e => e.target.style.borderColor = "rgba(0,0,0,0.15)"}
                />
              </div>
              <div style={{ marginBottom: "20px" }}>
                <label style={{ display: "block", fontSize: "0.75rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.08em", color: "#000", marginBottom: "10px" }}>
                  Delivery Address *
                </label>
                <textarea
                  value={address}
                  onChange={e => setAddress(e.target.value)}
                  placeholder="House No, Street, Area"
                  rows={3}
                  style={{ width: "100%", padding: "14px 16px", border: "1.5px solid rgba(0,0,0,0.15)", borderRadius: "10px", fontSize: "0.95rem", fontFamily: "inherit", outline: "none", resize: "vertical", transition: "border 0.2s" }}
                  onFocus={e => e.target.style.borderColor = "#000"}
                  onBlur={e => e.target.style.borderColor = "rgba(0,0,0,0.15)"}
                />
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div style={{ padding: "20px 32px 32px", display: "flex", gap: "12px" }}>
          <button onClick={handleConfirm} style={{
            flex: 1, padding: "16px", background: "#000", color: "#fff", border: "none",
            borderRadius: "12px", fontWeight: 700, fontSize: "0.92rem", textTransform: "uppercase",
            letterSpacing: "0.06em", cursor: "pointer", fontFamily: "inherit", transition: "background 0.2s",
          }}
          onMouseEnter={e => (e.currentTarget.style.background = "#333")}
          onMouseLeave={e => (e.currentTarget.style.background = "#000")}>
            Confirm {activeTab === "pickup" ? "Pickup" : "Delivery"}
          </button>
        </div>
      </div>
    </>
  );
}

function Hero() {
  const [current, setCurrent] = useState(0);
  const [slides, setSlides]   = useState<{ src: string; label: string }[]>([]);
  const [loaded, setLoaded]   = useState(false);
  const [siteName, setSiteName] = useState("SNACK ZONE");
  const [siteTagline, setSiteTagline] = useState("Fresh, Fast & Delicious");

  useEffect(() => {
    fetch(`${API_BASE}/get_slides.php`, { cache: "no-store" })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.slides?.length > 0) {
          setSlides(data.slides.map((s: { image_path: string; label: string }) => ({
            src:   API_BASE.replace(/\/Admin\/?$/, "") + "/" + s.image_path,
            label: s.label || "",
          })));
        }
      })
      .catch(() => {})
      .finally(() => setLoaded(true));
  }, []);

  // Fetch site branding
  useEffect(() => {
    fetch(`${API_BASE}/action.php?action=get_site_settings`)
      .then(r => r.json())
      .then(data => {
        if (data?.success && data?.settings) {
          if (data.settings.site_name) setSiteName(data.settings.site_name);
          if (data.settings.site_tagline) setSiteTagline(data.settings.site_tagline);
        }
      })
      .catch(() => {/* fallback to defaults */});
  }, []);

  const total = slides.length;

  useEffect(() => {
    if (total === 0) return; // No slides, skip interval
    const timer = setInterval(() => {
      setCurrent(c => (c + 1) % total);
    }, 4000);
    return () => clearInterval(timer);
  }, [total]);

  // Navigation functions
  const goToPrevious = () => {
    setCurrent(c => (c - 1 + total) % total);
  };

  const goToNext = () => {
    setCurrent(c => (c + 1) % total);
  };

  if (!loaded) {
    // Show placeholder with same dimensions to prevent CLS
    return (
      <section style={{ position: "relative", width: "100%", overflow: "hidden", background: "#111" }}>
        <div style={{ position: "relative", width: "100%", aspectRatio: "16/6", display: "flex", alignItems: "center", justifyContent: "center" }}>
          <div style={{ width: "48px", height: "48px", border: "3px solid rgba(255,255,255,0.2)", borderTopColor: "#fff", borderRadius: "50%", animation: "spin 0.8s linear infinite" }} />
        </div>
      </section>
    );
  }

  if (slides.length === 0) {
    // No slides — show fallback
    return (
      <section style={{ position: "relative", width: "100%", overflow: "hidden", background: "#000" }}>
        <div style={{ position: "relative", width: "100%", aspectRatio: "16/6", display: "flex", alignItems: "center", justifyContent: "center" }}>
          <div style={{ textAlign: "center", color: "#fff" }}>
            <h1 style={{ fontSize: "2.5rem", fontWeight: 900, margin: 0 }}>{siteName}</h1>
            <p style={{ fontSize: "1.1rem", margin: "8px 0 0", opacity: 0.8 }}>{siteTagline}</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section style={{ position: "relative", width: "100%", overflow: "hidden", background: "#000" }}>
      {/* Slides */}
      <div style={{ position: "relative", width: "100%", aspectRatio: "16/6" }}>
        {slides.map((slide, i) => (
          <div
            key={i}
            style={{
              position: "absolute", inset: 0,
              opacity: i === current ? 1 : 0,
              transition: "opacity 0.8s ease",
              pointerEvents: i === current ? "auto" : "none",
            }}
          >
            <Image
              src={slide.src}
              alt={slide.label || "Snack Zone"}
              fill
              style={{ objectFit: "cover" }}
              priority={i === 0}
              loading={i === 0 ? undefined : "lazy"}
              unoptimized
            />
          </div>
        ))}

        {/* Previous Arrow */}
        {slides.length > 1 && (
          <button
            onClick={goToPrevious}
            aria-label="Previous slide"
            style={{
              position: "absolute",
              left: "20px",
              top: "50%",
              transform: "translateY(-50%)",
              zIndex: 10,
              width: "50px",
              height: "50px",
              borderRadius: "50%",
              background: "rgba(0,0,0,0.5)",
              backdropFilter: "blur(4px)",
              border: "2px solid rgba(255,255,255,0.3)",
              color: "#fff",
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              transition: "all 0.3s ease",
              fontSize: "20px",
              fontWeight: "bold",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.background = "rgba(0,0,0,0.8)";
              e.currentTarget.style.transform = "translateY(-50%) scale(1.1)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.background = "rgba(0,0,0,0.5)";
              e.currentTarget.style.transform = "translateY(-50%) scale(1)";
            }}
          >
            ‹
          </button>
        )}

        {/* Next Arrow */}
        {slides.length > 1 && (
          <button
            onClick={goToNext}
            aria-label="Next slide"
            style={{
              position: "absolute",
              right: "20px",
              top: "50%",
              transform: "translateY(-50%)",
              zIndex: 10,
              width: "50px",
              height: "50px",
              borderRadius: "50%",
              background: "rgba(0,0,0,0.5)",
              backdropFilter: "blur(4px)",
              border: "2px solid rgba(255,255,255,0.3)",
              color: "#fff",
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              transition: "all 0.3s ease",
              fontSize: "20px",
              fontWeight: "bold",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.background = "rgba(0,0,0,0.8)";
              e.currentTarget.style.transform = "translateY(-50%) scale(1.1)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.background = "rgba(0,0,0,0.5)";
              e.currentTarget.style.transform = "translateY(-50%) scale(1)";
            }}
          >
            ›
          </button>
        )}

        {/* Slide label */}
        <div style={{
          position: "absolute", bottom: "48px", left: "50%", transform: "translateX(-50%)",
          zIndex: 10, textAlign: "center",
        }}>
          <span style={{
            background: "rgba(0,0,0,0.55)", color: "#fff",
            fontSize: "clamp(1rem, 3vw, 1.6rem)", fontWeight: 900,
            textTransform: "uppercase", letterSpacing: "0.08em",
            padding: "8px 24px", backdropFilter: "blur(4px)",
          }}>
            {slides[current]?.label}
          </span>
        </div>

        {/* Dots */}
        <div style={{
          position: "absolute", bottom: "16px", left: "50%", transform: "translateX(-50%)",
          display: "flex", gap: "8px", zIndex: 10,
        }}>
          {slides.map((_, i) => (
            <button
              key={i}
              onClick={() => setCurrent(i)}
              aria-label={`Slide ${i + 1}`}
              style={{
                width: i === current ? "24px" : "8px",
                height: "8px",
                borderRadius: i === current ? "4px" : "50%",
                background: i === current ? "#fff" : "rgba(255,255,255,0.45)",
                border: "none", cursor: "pointer",
                transition: "width 0.3s ease, background 0.3s ease",
                padding: 0,
              }}
            />
          ))}
        </div>
      </div>
    </section>
  );
}

// ── Item Detail Modal ────────────────────────────────────
function ItemModal({ item, cart, addToCart, updateQty, onClose }: {
  item: MenuItem;
  cart: CartItem[];
  addToCart: (item: MenuItem, addons: { id: number; name: string; price: number }[]) => void;
  updateQty: (id: number, qty: number) => void;
  onClose: () => void;
}) {
  const src    = item.image_path ? API_BASE.replace(/\/Admin\/?$/, "") + "/" + item.image_path : "";
  const inCart = cart.find(c => c.id === item.id);
  const [selectedAddons, setSelectedAddons] = useState<{ id: number; name: string; price: number }[]>([]);
  const [specialNote, setSpecialNote] = useState("");

  // Calculate discount
  const originalPrice = Number(item.price);
  const hasDiscount = item.discount_percent > 0;
  const basePrice = hasDiscount ? Math.round(originalPrice * (1 - item.discount_percent / 100)) : originalPrice;
  const addonsTotal = selectedAddons.reduce((s, a) => s + a.price, 0);
  const totalPrice  = basePrice + addonsTotal;

  const toggleAddon = (addon: { id: number; name: string; price: number }) => {
    setSelectedAddons(prev =>
      prev.find(a => a.id === addon.id)
        ? prev.filter(a => a.id !== addon.id)
        : [...prev, addon]
    );
  };

  // Close on Escape key
  useEffect(() => {
    const handler = (e: KeyboardEvent) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", handler);
    return () => window.removeEventListener("keydown", handler);
  }, [onClose]);

  return (
    <>
      {/* Overlay */}
      <div onClick={onClose} style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.6)", zIndex: 1000, animation: "fadeIn 0.2s ease" }} />

      {/* Modal */}
      <div className="addon-modal" style={{
        position: "fixed", top: "50%", left: "50%",
        transform: "translate(-50%, -50%)",
        zIndex: 1001, background: "#fff",
        borderRadius: "16px", overflow: "hidden",
        width: "96vw", maxWidth: "1300px",
        height: "90vh",
        boxShadow: "0 24px 60px rgba(0,0,0,0.35)",
        animation: "modalIn 0.22s ease",
        display: "flex", flexDirection: "column",
      }}>
        {/* Close button */}
        <button onClick={onClose} style={{
          position: "absolute", top: "12px", right: "12px", zIndex: 10,
          width: "34px", height: "34px", borderRadius: "50%",
          background: "rgba(0,0,0,0.6)", border: "none", cursor: "pointer",
          display: "flex", alignItems: "center", justifyContent: "center",
        }}>
          <X className="w-4 h-4 text-white" />
        </button>

        <div className="addon-modal-body" style={{ display: "flex", flexDirection: "row", height: "100%" }}>
          {/* Image — left */}
          <div className="addon-modal-img" style={{ position: "relative", width: "60%", minWidth: "60%", height: "100%", background: "#f5f5f5", flexShrink: 0 }}>
            {src ? (
              <Image src={src} alt={item.title} fill style={{ objectFit: "cover" }} unoptimized />
            ) : (
              <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center" }}>
                <span style={{ fontSize: "4rem", color: "#ddd", fontWeight: 900 }}>{item.title.charAt(0)}</span>
              </div>
            )}
            {item.tag && (
              <span style={{ position: "absolute", top: "12px", left: "12px", background: "#000", color: "#fff", fontSize: "0.6rem", fontWeight: 800, letterSpacing: "0.06em", textTransform: "uppercase", padding: "4px 10px", borderRadius: "4px" }}>
                {item.tag}
              </span>
            )}
          </div>

          {/* Info — right */}
          <div className="addon-modal-info" style={{ flex: 1, padding: "32px 28px", display: "flex", flexDirection: "column", gap: "14px", minWidth: 0, overflowY: "auto" }}>

            {/* Title + price */}
            <div>
              <h2 style={{ fontWeight: 900, fontSize: "1.3rem", textTransform: "uppercase", letterSpacing: "-0.01em", color: "#000", margin: "0 0 8px" }}>
                {item.title}
              </h2>
              
              {/* Price with discount */}
              <div style={{ marginBottom: "10px" }}>
                {hasDiscount ? (
                  <>
                    {/* Discount Badge */}
                    <span style={{
                      display: "inline-block",
                      background: "#dc2626",
                      color: "#fff",
                      fontSize: "0.7rem",
                      fontWeight: 900,
                      letterSpacing: "0.05em",
                      textTransform: "uppercase",
                      padding: "4px 10px",
                      borderRadius: "4px",
                      marginBottom: "8px"
                    }}>
                      {item.discount_percent}% OFF
                    </span>
                    
                    {/* Discounted Price */}
                    <div style={{ display: "flex", alignItems: "baseline", gap: "10px" }}>
                      <p style={{ fontWeight: 900, fontSize: "1.5rem", color: "#dc2626", margin: 0 }}>
                        Rs. {basePrice.toLocaleString()}
                      </p>
                      <p style={{ fontSize: "1.1rem", color: "#999", textDecoration: "line-through", margin: 0 }}>
                        Rs. {originalPrice.toLocaleString()}
                      </p>
                    </div>
                  </>
                ) : (
                  <p style={{ fontWeight: 900, fontSize: "1.3rem", color: "#e05c00", margin: 0 }}>
                    Rs. {basePrice.toLocaleString()}
                  </p>
                )}
              </div>
              
              {item.description && (
                <p style={{ fontSize: "0.85rem", color: "#666", lineHeight: 1.6, margin: 0 }}>
                  {item.description}
                </p>
              )}
            </div>

            {/* Add-ons */}
            {item.addons && item.addons.length > 0 && (
              <div>
                <p style={{ fontSize: "0.7rem", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", color: "#000", marginBottom: "10px", paddingBottom: "8px", borderBottom: "2px solid #000" }}>
                  Add-ons
                </p>
                <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                  {item.addons.map(addon => {
                    const checked = !!selectedAddons.find(a => a.id === addon.id);
                    return (
                      <label key={addon.id} onClick={() => toggleAddon(addon)} style={{
                        display: "flex", alignItems: "center", justifyContent: "space-between",
                        padding: "10px 14px", cursor: "pointer", borderRadius: "8px",
                        border: `1px solid ${checked ? "#000" : "rgba(0,0,0,0.1)"}`,
                        background: checked ? "#f5f5f5" : "#fff",
                        transition: "all 0.15s",
                      }}>
                        <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
                          <div style={{
                            width: "18px", height: "18px", borderRadius: "4px",
                            border: `2px solid ${checked ? "#000" : "#ccc"}`,
                            background: checked ? "#000" : "#fff",
                            display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0,
                          }}>
                            {checked && <span style={{ color: "#fff", fontSize: "11px", fontWeight: 900, lineHeight: 1 }}>✓</span>}
                          </div>
                          <span style={{ fontSize: "0.88rem", fontWeight: 600, color: "#000" }}>{addon.name}</span>
                        </div>
                        <span style={{ fontSize: "0.85rem", fontWeight: 700, color: "#e05c00" }}>
                          + Rs. {Number(addon.price).toLocaleString()}
                        </span>
                      </label>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Spacer */}
            <div style={{ flex: 1 }} />

            {/* Special Instructions */}
            <div style={{ marginBottom: "12px" }}>
              <label style={{
                display: "block",
                fontSize: "0.7rem", fontWeight: 800,
                textTransform: "uppercase", letterSpacing: "0.08em",
                color: "#374151", marginBottom: "6px",
              }}>
                Special Instructions <span style={{ color: "#aaa", fontWeight: 400, textTransform: "none", letterSpacing: 0 }}>(optional)</span>
              </label>
              <textarea
                value={specialNote}
                onChange={e => setSpecialNote(e.target.value)}
                placeholder="e.g. Extra sauce, no onions, less spicy..."
                rows={2}
                style={{
                  width: "100%", padding: "10px 12px",
                  border: "1.5px solid rgba(0,0,0,0.12)",
                  borderRadius: "8px", resize: "none",
                  fontFamily: "inherit", fontSize: "0.85rem",
                  color: "#111", outline: "none",
                  transition: "border-color 0.2s",
                  boxSizing: "border-box",
                }}
                onFocus={e => e.target.style.borderColor = "#000"}
                onBlur={e => e.target.style.borderColor = "rgba(0,0,0,0.12)"}
              />
            </div>

            {/* Total + Add to cart */}
            <div>
              {selectedAddons.length > 0 && (
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "10px 0", borderTop: "1px solid rgba(0,0,0,0.08)", marginBottom: "12px" }}>
                  <span style={{ fontSize: "0.82rem", fontWeight: 700, color: "#888", textTransform: "uppercase", letterSpacing: "0.06em" }}>Total</span>
                  <span style={{ fontSize: "1.2rem", fontWeight: 900, color: "#000" }}>Rs. {totalPrice.toLocaleString()}</span>
                </div>
              )}

              {inCart ? (
                <div>
                  <div style={{ display: "flex", alignItems: "center", gap: "12px", marginBottom: "12px" }}>
                    <button onClick={() => updateQty(item.id, inCart.qty - 1)} style={{ width: "40px", height: "40px", background: "#f0f0f0", border: "none", borderRadius: "10px", cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center" }}>
                      <Minus className="w-4 h-4" />
                    </button>
                    <span style={{ fontWeight: 900, fontSize: "1.2rem", minWidth: "28px", textAlign: "center" }}>{inCart.qty}</span>
                    <button onClick={() => updateQty(item.id, inCart.qty + 1)} style={{ width: "40px", height: "40px", background: "#000", border: "none", borderRadius: "10px", cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center" }}>
                      <Plus className="w-4 h-4 text-white" />
                    </button>
                    <span style={{ fontSize: "0.85rem", color: "#888" }}>= Rs. {(totalPrice * inCart.qty).toLocaleString()}</span>
                  </div>
                  <button onClick={onClose} style={{ width: "100%", padding: "14px 0", background: "#000", color: "#fff", border: "none", borderRadius: "10px", cursor: "pointer", fontSize: "0.82rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.06em", fontFamily: "inherit" }}>
                    Done ✓
                  </button>
                </div>
              ) : (
                <button onClick={() => { addToCart(item, selectedAddons); onClose(); }} style={{ width: "100%", padding: "16px 0", background: "#000", color: "#fff", border: "none", borderRadius: "10px", cursor: "pointer", fontSize: "0.88rem", fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.08em", display: "flex", alignItems: "center", justifyContent: "center", gap: "8px", fontFamily: "inherit" }}>
                  <Plus className="w-4 h-4" /> Add to Cart — Rs. {totalPrice.toLocaleString()}
                </button>              )}
            </div>

          </div>
        </div>
      </div>
    </>
  );
}

// ── Reusable menu item card ───────────────────────────────
function MenuCard({ item, cart, addToCart, updateQty }: {
  item: MenuItem;
  cart: CartItem[];
  addToCart: (item: MenuItem, addons?: { id: number; name: string; price: number }[]) => void;
  updateQty: (id: number, qty: number) => void;
}) {
  const src    = item.image_path ? API_BASE.replace(/\/Admin\/?$/, "") + "/" + item.image_path : "";
  const inCart = cart.find(c => c.id === item.id);
  const [modal, setModal] = useState(false);

  const hasDiscount = item.discount_percent > 0;
  const finalPrice  = hasDiscount
    ? Math.round(Number(item.price) * (1 - item.discount_percent / 100))
    : Number(item.price);

  const BROWN  = "#4a2417"; 
  const ORANGE = "#ff6a13";

  return (
    <>
      {modal && (
        <ItemModal
          item={item} cart={cart}
          addToCart={addToCart} updateQty={updateQty}
          onClose={() => setModal(false)}
        />
      )}

      <motion.div
        style={{
          display: "flex",
          alignItems: "center",
          gap: "20px",
          padding: "20px",
          background: "#fff",
          borderRadius: "20px",
          boxShadow: "0 2px 12px rgba(0,0,0,0.08)",
        }}
        initial={{ opacity: 0, y: 30 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, amount: 0.2 }}
        transition={{ duration: 0.5, ease: "easeOut" }}
        whileHover={{ boxShadow: "0 8px 24px rgba(0,0,0,0.14)", transition: { duration: 0.2 } }}
      >
        {/* ── Left: image ── */}
        <div
          onClick={() => setModal(true)}
          style={{ position: "relative", flex: "0 0 47%", aspectRatio: "1 / 1", cursor: "pointer" }}
        >
          {src ? (
            <Image src={src} alt={item.title} fill style={{ objectFit: "contain" }} unoptimized />
          ) : (
            <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center", background: "#f5f5f5", borderRadius: "12px" }}>
              <span style={{ fontWeight: 800, color: "#ddd", fontSize: "2.5rem" }}>{item.title.charAt(0)}</span>
            </div>
          )}
          {item.tag && (
            <span style={{ position: "absolute", top: 0, left: 0, background: "#000", color: "#fff", fontSize: "0.6rem", fontWeight: 700, padding: "3px 8px", borderRadius: "6px" }}>
              {item.tag}
            </span>
          )}
          {hasDiscount && (
            <span style={{ position: "absolute", bottom: 0, left: 0, background: "#dc2626", color: "#fff", fontSize: "0.7rem", fontWeight: 700, padding: "3px 10px", borderRadius: "999px" }}>
              {item.discount_percent}% OFF
            </span>
          )}
        </div>

        {/* ── Right: details ── */}
        <div style={{ flex: 1, minWidth: 0, display: "flex", flexDirection: "column", gap: "8px" }}>
          <h3
            onClick={() => setModal(true)}
            style={{ margin: 0, fontWeight: 700, fontSize: "1.15rem", color: "#111", cursor: "pointer", lineHeight: 1.25 }}
          >
            {item.title}
          </h3>

          {item.description && (
            <p style={{
              margin: 0, fontSize: "0.78rem", color: "#888", lineHeight: 1.5,
              textTransform: "uppercase", letterSpacing: "0.02em",
              display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical",
              overflow: "hidden", textOverflow: "ellipsis",
            } as React.CSSProperties}>
              {item.description}
            </p>
          )}

          {/* Price */}
          <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
            <span style={{ background: ORANGE, color: "#fff", fontWeight: 600, fontSize: "0.85rem", padding: "3px 12px", borderRadius: "8px" }}>
              Rs. {finalPrice.toLocaleString()}.00
            </span>
            {hasDiscount && (
              <span style={{ fontSize: "0.8rem", color: "#999", textDecoration: "line-through" }}>
                Rs. {Number(item.price).toLocaleString()}
              </span>
            )}
          </div>

          {/* Add to Cart + Heart */}
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "10px", marginTop: "4px" }}>
            {inCart ? (
              <div style={{ display: "flex", alignItems: "center", gap: "10px", background: BROWN, borderRadius: "10px", padding: "6px 10px" }}>
                <button onClick={() => updateQty(item.id, inCart.qty - 1)}
                  style={{ background: "transparent", border: "none", color: "#fff", cursor: "pointer", display: "flex" }}>
                  <Minus className="w-4 h-4" />
                </button>
                <span style={{ color: "#fff", fontWeight: 700, minWidth: "20px", textAlign: "center" }}>{inCart.qty}</span>
                <button onClick={() => updateQty(item.id, inCart.qty + 1)}
                  style={{ background: "transparent", border: "none", color: "#fff", cursor: "pointer", display: "flex" }}>
                  <Plus className="w-4 h-4" />
                </button>
              </div>
            ) : (
              <button
                onClick={() => setModal(true)}
                style={{
                  background: BROWN, color: "#fff", border: "none", borderRadius: "10px",
                  padding: "12px 22px", fontSize: "0.9rem", fontWeight: 700,
                  cursor: "pointer", fontFamily: "inherit", whiteSpace: "nowrap",
                }}
              >
                Add To Cart
              </button>
            )}


          </div>
        </div>
      </motion.div>
    </>
  );
}

export default function HomePage() {
  // Menu state
  const [categories,   setCategories]   = useState<CategoryGroup[]>([]);
  const [activeSlug,   setActiveSlug]   = useState("all");
  const [search,       setSearch]       = useState("");
  const [itemModal,    setItemModal]    = useState<MenuItem | null>(null);

  // Animation variants
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1
      }
    }
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: {
      opacity: 1,
      y: 0,
      transition: {
        duration: 0.5,
        ease: "easeOut"
      }
    }
  };

  const categoryVariants = {
    hidden: { opacity: 0, x: -30 },
    visible: {
      opacity: 1,
      x: 0,
      transition: {
        duration: 0.6,
        ease: "easeOut"
      }
    }
  };

  const bannerVariants = {
    hidden: { opacity: 0, scale: 0.95 },
    visible: {
      opacity: 1,
      scale: 1,
      transition: {
        duration: 0.7,
        ease: "easeOut"
      }
    }
  };
  const [menuLoading,  setMenuLoading]  = useState(true);

  // Cart state
  const [cart, setCart]           = useState<CartItem[]>([]);
  const [cartOpen, setCartOpen]   = useState(false);
  const [cartFlash, setCartFlash] = useState(false);

  // Delivery modal state
  const [deliveryModalOpen, setDeliveryModalOpen] = useState(false);
  const [pickupLocation, setPickupLocation] = useState("Snack Zone, Main Street, Karachi");
  const [modalReady, setModalReady] = useState(false);  

  const totalItems = cart.reduce((s, i) => s + i.qty, 0);
  const totalPrice = cart.reduce((s, i) => {
    const basePrice = i.price * i.qty;
    const addonsPrice = (i.selectedAddons || []).reduce((sum, a) => sum + a.price, 0) * i.qty;
    return s + basePrice + addonsPrice;
  }, 0);

  const addToCart = useCallback((item: MenuItem, addons: { id: number; name: string; price: number }[] = []) => {
    setCart(prev => {
      const existing = prev.find(i => i.id === item.id);
      if (existing) return prev.map(i => i.id === item.id ? { ...i, qty: i.qty + 1 } : i);
      
      // Calculate discounted price when adding to cart
      const originalPrice = Number(item.price);
      const hasDiscount = item.discount_percent > 0;
      const effectivePrice = hasDiscount ? Math.round(originalPrice * (1 - item.discount_percent / 100)) : originalPrice;
      
      return [...prev, { 
        ...item, 
        price: effectivePrice, // Use discounted price
        qty: 1, 
        selectedAddons: addons 
      }];
    });
    setCartFlash(true);
    setTimeout(() => setCartFlash(false), 800);
  }, []);

  const removeFromCart = useCallback((id: number) => setCart(prev => prev.filter(i => i.id !== id)), []);
  const updateQty      = useCallback((id: number, qty: number) => {
    if (qty <= 0) { setCart(prev => prev.filter(i => i.id !== id)); return; }
    setCart(prev => prev.map(i => i.id === id ? { ...i, qty } : i));
  }, []);
  const clearCart = useCallback(() => setCart([]), []);

  // Lock body scroll when cart open
  useEffect(() => {
    document.body.style.overflow = cartOpen ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [cartOpen]);

  // Fetch menu categories
  useEffect(() => {
    fetch(`${API_BASE}/get_menu.php`, { cache: "no-store" })
      .then(r => r.json())
      .then((data: { success: boolean; categories: CategoryGroup[] }) => {
        if (data.success && data.categories?.length) {
          setCategories(data.categories);
          // don't set activeSlug — stays "all" by default
        }
      })
      .catch(() => {})
      .finally(() => setMenuLoading(false));
  }, []);

  // Check delivery modal settings and auto-open (client-side only)
  useEffect(() => {
    setModalReady(true); // Component mounted
    const hasSeenModal = localStorage.getItem("sz_delivery_choice");
    if (hasSeenModal) return; // User already chose

    fetch(`${API_BASE}/action.php?action=get_delivery_modal_settings`)
      .then(r => r.json())
      .then((data: { success: boolean; is_enabled: boolean; pickup_location: string }) => {
        if (data.success && data.is_enabled) {
          setPickupLocation(data.pickup_location || "Snack Zone, Main Street, Karachi");
          setDeliveryModalOpen(true);
        }
      })
      .catch(() => {});
  }, []);

  const handleDeliveryChoice = (type: "pickup" | "delivery", city?: string, address?: string) => {
    const choice = { type, city, address, timestamp: Date.now() };
    localStorage.setItem("sz_delivery_choice", JSON.stringify(choice));
    setDeliveryModalOpen(false);
  };

  // Filtered items — memoized to prevent recalculation
  const activeItems = useMemo(() => {
    const allItems = categories.flatMap(c => c.items);
    if (search.trim()) {
      const searchLower = search.toLowerCase();
      return allItems.filter(i =>
        i.title.toLowerCase().includes(searchLower) ||
        i.description?.toLowerCase().includes(searchLower)
      );
    }
    return activeSlug === "all"
      ? allItems
      : (categories.find(c => c.slug === activeSlug)?.items ?? []);
  }, [categories, activeSlug, search]);

  return (
    <div className="bg-white">
      {/* Delivery/Pickup Modal — only render after client mount */}
      {modalReady && (
        <DeliveryModal
          isOpen={deliveryModalOpen}
          onClose={handleDeliveryChoice}
          pickupLocation={pickupLocation}
        />
      )}

      <Hero />

      {/* ── Featured Deals Section ── */}
      {!menuLoading && (() => {
        const featuredItems = categories.flatMap(c => c.items).filter(i => i.featured);
        if (featuredItems.length === 0) return null;
        
        return (
          <section style={{ background: "#f9f9f9", padding: "40px 20px" }}>
            <div style={{ maxWidth: "1100px", margin: "0 auto" }}>
              <div style={{ textAlign: "center", marginBottom: "32px" }}>
                <h2 style={{ 
                  fontWeight: 900, 
                  fontSize: "2rem", 
                  textTransform: "uppercase", 
                  letterSpacing: "0.04em", 
                  color: "#000",
                  margin: "0 0 8px"
                }}>
                  🔥 Special Offers
                </h2>
                <p style={{ 
                  fontSize: "0.95rem", 
                  color: "#666",
                  margin: 0
                }}>
                  Limited Time Deals - Don't Miss Out!
                </p>
              </div>
              
              <div style={{ 
                display: "grid", 
                gridTemplateColumns: "repeat(auto-fill, minmax(300px, 1fr))", 
                gap: "24px"
              }}>
                {featuredItems.map(item => {
                  const originalPrice = Number(item.price);
                  const hasDiscount = item.discount_percent > 0;
                  const discountedPrice = hasDiscount ? Math.round(originalPrice * (1 - item.discount_percent / 100)) : originalPrice;
                  const src = item.image_path ? imgSrc(item.image_path) : "";
                  
                  return (
                    <motion.div
                      key={item.id}
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.4 }}
                      style={{
                        background: "#fff",
                        borderRadius: "12px",
                        overflow: "hidden",
                        boxShadow: "0 2px 8px rgba(0,0,0,0.1)",
                        cursor: "pointer",
                        transition: "transform 0.2s, box-shadow 0.2s",
                        border: "1px solid rgba(0,0,0,0.05)"
                      }}
                      whileHover={{ 
                        transform: "translateY(-4px)",
                        boxShadow: "0 8px 20px rgba(0,0,0,0.15)"
                      }}
                      onClick={() => setItemModal(item)}
                    >
                      {/* Image */}
                      <div style={{ position: "relative", width: "100%", aspectRatio: "4/3", background: "#f5f5f5" }}>
                        {src ? (
                          <Image src={src} alt={item.title} fill style={{ objectFit: "cover" }} unoptimized />
                        ) : (
                          <div style={{ 
                            position: "absolute", 
                            inset: 0, 
                            background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center"
                          }}>
                            <span style={{ fontWeight: 900, color: "#fff", fontSize: "3rem", opacity: 0.5 }}>
                              {item.title.charAt(0)}
                            </span>
                          </div>
                        )}
                        
                        {/* Discount Badge - Top Right */}
                        {hasDiscount && (
                          <span style={{
                            position: "absolute",
                            top: "10px",
                            right: "10px",
                            background: "#dc2626",
                            color: "#fff",
                            fontSize: "0.75rem",
                            fontWeight: 900,
                            letterSpacing: "0.03em",
                            padding: "4px 10px",
                            borderRadius: "4px",
                            boxShadow: "0 2px 8px rgba(220,38,38,0.3)"
                          }}>
                            {item.discount_percent}% OFF
                          </span>
                        )}

                        {/* Category Label - Bottom */}
                        <div style={{
                          position: "absolute",
                          bottom: 0,
                          left: 0,
                          right: 0,
                          background: "#000",
                          color: "#fff",
                          fontSize: "0.7rem",
                          fontWeight: 700,
                          letterSpacing: "0.08em",
                          textTransform: "uppercase",
                          padding: "8px 12px",
                          textAlign: "center"
                        }}>
                          {item.category.replace(/_/g, " ")}
                        </div>
                      </div>

                      {/* Content */}
                      <div style={{ padding: "16px" }}>
                        {/* Title */}
                        <h3 style={{
                          fontWeight: 700,
                          fontSize: "1rem",
                          color: "#000",
                          margin: "0 0 8px",
                          lineHeight: 1.3,
                          minHeight: "40px"
                        }}>
                          {item.title}
                        </h3>

                        {/* Price Section */}
                        <div style={{ marginTop: "12px" }}>
                          {hasDiscount ? (
                            <>
                              <div style={{ display: "flex", alignItems: "baseline", gap: "8px", marginBottom: "4px" }}>
                                <span style={{
                                  fontSize: "0.7rem",
                                  color: "#999",
                                  textTransform: "uppercase",
                                  letterSpacing: "0.05em",
                                  fontWeight: 600
                                }}>
                                  Rs.
                                </span>
                                <span style={{
                                  fontWeight: 900,
                                  fontSize: "1.5rem",
                                  color: "#dc2626",
                                  lineHeight: 1
                                }}>
                                  Rs. {discountedPrice}
                                </span>
                              </div>
                              <div style={{
                                fontSize: "0.85rem",
                                color: "#999",
                                textDecoration: "line-through"
                              }}>
                                Rs. {originalPrice}
                              </div>
                            </>
                          ) : (
                            <div style={{ display: "flex", alignItems: "baseline", gap: "8px" }}>
                              <span style={{
                                fontSize: "0.7rem",
                                color: "#999",
                                textTransform: "uppercase",
                                letterSpacing: "0.05em",
                                fontWeight: 600
                              }}>
                                Rs.
                              </span>
                              <span style={{
                                fontWeight: 900,
                                fontSize: "1.5rem",
                                color: "#000",
                                lineHeight: 1
                              }}>
                                Rs. {originalPrice}
                              </span>
                            </div>
                          )}
                        </div>

                        {/* Add to Cart Button */}
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            addToCart(item);
                          }}
                          style={{
                            width: "100%",
                            marginTop: "16px",
                            padding: "12px",
                            background: "#000",
                            color: "#fff",
                            border: "none",
                            borderRadius: "6px",
                            fontWeight: 700,
                            fontSize: "0.8rem",
                            textTransform: "uppercase",
                            letterSpacing: "0.06em",
                            cursor: "pointer",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                            gap: "6px",
                            transition: "background 0.2s",
                            fontFamily: "inherit"
                          }}
                          onMouseEnter={(e) => (e.currentTarget.style.background = "#333")}
                          onMouseLeave={(e) => (e.currentTarget.style.background = "#000")}
                        >
                          <Plus className="w-4 h-4" />
                          Add to Cart
                        </button>
                      </div>
                    </motion.div>
                  );
                })}
              </div>
            </div>
          </section>
        );
      })()}

      {/* ── Category Tabs ── */}
      <div style={{ background: "#fff", borderBottom: "1px solid rgba(0,0,0,0.1)", position: "sticky", top: "62px", zIndex: 40 }}>
        <div style={{ overflowX: "auto", scrollbarWidth: "none", display: "flex", alignItems: "center", justifyContent: "center" }}
          className="hide-scrollbar">
          {menuLoading ? (
            <div style={{ display: "flex", gap: "4px", padding: "0 16px", height: "46px", alignItems: "center" }}>
              {[...Array(5)].map((_, i) => (
                <div key={i} className="animate-pulse" style={{ height: "28px", width: `${70 + i * 15}px`, background: "#f0f0f0", borderRadius: "4px", flexShrink: 0 }} />
              ))}
            </div>
          ) : (
            <>
              {/* All tab */}
              <button
                onClick={() => { setActiveSlug("all"); setSearch(""); }}
                style={{
                  flexShrink: 0, padding: "12px 18px",
                  fontSize: "0.78rem", fontWeight: 700,
                  textTransform: "uppercase", letterSpacing: "0.05em",
                  background: "transparent", border: "none",
                  borderBottom: activeSlug === "all" && !search ? "2px solid #000" : "2px solid transparent",
                  color: activeSlug === "all" && !search ? "#000" : "#888",
                  cursor: "pointer", whiteSpace: "nowrap", fontFamily: "inherit",
                  transition: "color 0.15s, border-color 0.15s",
                }}
              >
                All
              </button>
              {/* Category tabs */}
              {categories.map(cat => (
                <button
                  key={cat.slug}
                  onClick={() => { setActiveSlug(cat.slug); setSearch(""); }}
                  style={{
                    flexShrink: 0, padding: "12px 18px",
                    fontSize: "0.78rem", fontWeight: 700,
                    textTransform: "uppercase", letterSpacing: "0.05em",
                    background: "transparent", border: "none",
                    borderBottom: activeSlug === cat.slug && !search ? "2px solid #000" : "2px solid transparent",
                    color: activeSlug === cat.slug && !search ? "#000" : "#888",
                    cursor: "pointer", whiteSpace: "nowrap", fontFamily: "inherit",
                    transition: "color 0.15s, border-color 0.15s",
                  }}
                >
                  {cat.name}
                </button>
              ))}
            </>
          )}
        </div>
      </div>

      {/* ── Search Bar ── */}
      <div style={{ background: "#fafafa", borderBottom: "1px solid rgba(0,0,0,0.07)", padding: "10px 20px" }}>
        <div style={{ maxWidth: "600px", position: "relative", margin: "0 auto" }}>
          <Search style={{ position: "absolute", left: "12px", top: "50%", transform: "translateY(-50%)", width: "15px", height: "15px", color: "#bbb", pointerEvents: "none" }} />
          <input
            type="search"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder={`Search `}
            style={{
              width: "100%", height: "40px",
              padding: "0 14px 0 36px",
              fontSize: "0.85rem", fontFamily: "inherit",
              color: "#000", background: "#fff",
              border: "1px solid rgba(0,0,0,0.13)",
              borderRadius: "4px", outline: "none",
            }}
          />
        </div>
      </div>

      {/* ── Menu Items — 3 per row grid ── */}
      <div style={{ background: "#fff", padding: "20px 20px 100px" }}>
        <div style={{ maxWidth: "1100px", margin: "0 auto" }}>

          {/* Skeleton */}
          {menuLoading && (
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: "16px" }}>
              {[...Array(6)].map((_, i) => (
                <div key={i} className="animate-pulse" style={{ borderRadius: "12px", overflow: "hidden", background: "#f5f5f5", height: "280px" }} />
              ))}
            </div>
          )}

          {/* Empty */}
          {!menuLoading && activeItems.length === 0 && (
            <p style={{ textAlign: "center", color: "#aaa", padding: "3rem 0", fontSize: "0.88rem" }}>
              {search ? `No results for "${search}"` : "No items in this category."}
            </p>
          )}

          {/* Category sections — banner + grid per category */}
          {!menuLoading && (
            search.trim() ? (
              /* Search results — no banners, flat grid */
              activeItems.length === 0 ? (
                <p style={{ textAlign: "center", color: "#aaa", padding: "3rem 0", fontSize: "0.88rem" }}>
                  No results for &ldquo;{search}&rdquo;
                </p>
              ) : (
                <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: "16px" }} className="home-menu-grid">
                  {activeItems.map(item => <MenuCard key={item.id} item={item} cart={cart} addToCart={addToCart} updateQty={updateQty} />)}
                </div>
              )
            ) : (
              /* Category view — banner then items for each visible category */
              (() => {
                const visibleCats = activeSlug === "all"
                  ? categories
                  : categories.filter(c => c.slug === activeSlug);
                if (visibleCats.length === 0)
                  return <p style={{ textAlign: "center", color: "#aaa", padding: "3rem 0", fontSize: "0.88rem" }}>No items found.</p>;
                return (
                  <motion.div
                    initial="hidden"
                    animate="visible"
                    variants={containerVariants}
                  >
                    {visibleCats.map((cat, catIndex) => (
                      <motion.div 
                        key={cat.slug} 
                        style={{ marginBottom: "36px" }}
                        initial={{ opacity: 0, x: -30 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        viewport={{ once: true, amount: 0.2 }}
                        transition={{ duration: 0.6, delay: catIndex * 0.1 }}
                      >
                        {/* Title first — show in single category view OR when banner exists in "all" view */}
                        {(activeSlug !== "all" || cat.banner_image) && (
                          <motion.h1 
                            style={{
                              fontWeight: 900, fontSize: activeSlug === "all" ? "1rem" : "1.4rem", 
                              textTransform: "uppercase",
                              letterSpacing: "0.02em", color: "#000", 
                              margin: activeSlug === "all" ? "0 0 12px" : "0 0 16px",
                            }}
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.5, delay: 0.1 }}
                          >
                            {cat.name}
                          </motion.h1>
                        )}
                        
                        {/* Banner below title */}
                        {cat.banner_image ? (
                          <motion.div 
                            style={{ position: "relative", width: "100%", aspectRatio: "16/5", minHeight: "140px", maxHeight: "260px", borderRadius: "12px", overflow: "hidden", background: "#111", marginBottom: "20px" }}
                            initial={{ opacity: 0, scale: 0.95 }}
                            whileInView={{ opacity: 1, scale: 1 }}
                            viewport={{ once: true, amount: 0.3 }}
                            transition={{ duration: 0.7, delay: 0.2 }}
                          >
                            <Image
                              src={API_BASE.replace(/\/Admin\/?$/, "") + "/" + cat.banner_image}
                              alt={cat.name} fill
                              style={{ objectFit: "cover", objectPosition: "center" }}
                              quality={80}
                              sizes="(max-width: 1100px) 100vw, 1100px"
                              loading="lazy"
                              unoptimized
                            />
                          </motion.div>
                        ) : activeSlug !== "all" && (
                          /* Fallback gradient banner if no image */
                          <motion.div 
                            style={{
                              position: "relative", width: "100%", minHeight: "180px",
                              borderRadius: "12px", overflow: "hidden",
                              background: "linear-gradient(135deg, #000 0%, #2d2d2d 100%)",
                              marginBottom: "20px",
                            }}
                            initial={{ opacity: 0, scale: 0.95 }}
                            whileInView={{ opacity: 1, scale: 1 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.7 }}
                          />
                        )}
                        
                        {/* Category name — only in "all" view (below banner) — REMOVED, now shown above */}
                        {activeSlug === "all" && !cat.banner_image && (
                          <motion.h2 
                            style={{ fontWeight: 900, fontSize: "1rem", textTransform: "uppercase", letterSpacing: "0.04em", color: "#000", marginBottom: "14px" }}
                            initial={{ opacity: 0, x: -20 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.5 }}
                          >
                            {cat.name}
                          </motion.h2>
                        )}
                        {/* Items grid */}
                        <motion.div 
                          style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: "16px" }} 
                          className="home-menu-grid"
                          initial="hidden"
                          whileInView="visible"
                          viewport={{ once: true, amount: 0.1 }}
                          variants={{
                            visible: {
                              transition: {
                                staggerChildren: 0.08
                              }
                            }
                          }}
                        >
                          {cat.items.map(item => <MenuCard key={item.id} item={item} cart={cart} addToCart={addToCart} updateQty={updateQty} />)}
                        </motion.div>
                      </motion.div>
                    ))}
                  </motion.div>
                );
              })()
            )
          )}

        </div>
      </div>

      {/* ── Floating Cart Button ── */}
      {totalItems > 0 && (
        <motion.button
          className={`cart-fab${cartFlash ? " cart-fab-flash" : ""}`}
          onClick={() => setCartOpen(true)}
          aria-label={`Open cart — ${totalItems} items`}
          initial={{ scale: 0, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          exit={{ scale: 0, opacity: 0 }}
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          transition={{ type: "spring", stiffness: 260, damping: 20 }}
        >
          <ShoppingCart className="w-5 h-5" />
          <span className="cart-fab-label">
            {totalItems} item{totalItems !== 1 ? "s" : ""} · Rs. {totalPrice.toLocaleString()}
          </span>
          <span className="cart-fab-badge">{totalItems}</span>
        </motion.button>
      )}

      {/* ── Cart Drawer ── */}
      {cartOpen && (
        <CartDrawer
          cart={cart} addToCart={addToCart} removeFromCart={removeFromCart}
          updateQty={updateQty} clearCart={clearCart}
          totalItems={totalItems} totalPrice={totalPrice}
          onClose={() => setCartOpen(false)}
        />
      )}
      
      {/* ── Item Detail Modal ── */}
      {itemModal && (
        <ItemModal
          item={itemModal}
          cart={cart}
          addToCart={addToCart}
          updateQty={updateQty}
          onClose={() => setItemModal(null)}
        />
      )}
    </div>
  );
}
