"use client";

import { useState, useMemo, useEffect, useCallback, useRef } from "react";
import Image from "next/image";
import {
  Search, ShoppingCart, Plus, Minus,
  ChevronRight, Phone, X, Trash2, CheckCircle, Loader2,
  Clock, ChefHat, Bike, XCircle,
} from "lucide-react";
import { API_BASE } from "@/lib/api";

// ── Types ─────────────────────────────────────────────────
interface MenuItem {
  id: number;
  title: string;
  category: string;
  description: string;
  price: number;
  discount_percent: number;
  image_path: string;
  tag: string;
  in_stock?: boolean;
}

interface CartItem extends MenuItem {
  qty: number;
}

// ── Image helper ──────────────────────────────────────────
function imgSrc(image_path: string): string {
  if (!image_path) return "";
  return API_BASE.replace(/\/Admin\/?$/, "") + "/" + image_path;
}

// ── Cart context (prop-drilled for simplicity) ────────────
interface CartActions {
  cart: CartItem[];
  addToCart: (item: MenuItem) => void;
  removeFromCart: (id: number) => void;
  updateQty: (id: number, qty: number) => void;
  clearCart: () => void;
  totalItems: number;
  totalPrice: number;
}

// ── Product card ──────────────────────────────────────────
function ProductCard({ item, onAdd }: { item: MenuItem; onAdd: (item: MenuItem) => void }) {
  const [added, setAdded]     = useState(false);
  const [imgError, setImgError] = useState(false);
  const [imgLoaded, setImgLoaded] = useState(false);
  const cardRef = useRef<HTMLDivElement>(null);
  const [isVisible, setIsVisible] = useState(false);
  const src = imgSrc(item.image_path);
  
  // Check stock status
  const inStock = item.in_stock !== false; // boolean check only
  
  useEffect(() => {
    console.log(`Item ${item.id} (${item.title}): in_stock =`, item.in_stock, 'inStock =', inStock);
  }, [item.id, item.title, item.in_stock, inStock]);
  
  // Calculate discount
  const originalPrice = item.price;
  const hasDiscount = item.discount_percent > 0;
  const displayPrice = hasDiscount ? Math.round(originalPrice * (1 - item.discount_percent / 100)) : originalPrice;

  // Intersection Observer for lazy loading
  useEffect(() => {
    if (!cardRef.current) return;
    
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setIsVisible(true);
            observer.disconnect();
          }
        });
      },
      { rootMargin: "50px" } // Start loading 50px before visible
    );

    observer.observe(cardRef.current);
    return () => observer.disconnect();
  }, []);

  const handleAdd = () => {
    if (!inStock) return; // Don't add if out of stock
    onAdd(item);
    setAdded(true);
    setTimeout(() => setAdded(false), 900);
  };

  return (
    <div ref={cardRef} style={{
      display: "flex", flexDirection: "row",
      border: "1px solid rgba(0,0,0,0.08)",
      borderRadius: "10px", overflow: "hidden",
      background: "#fff",
      boxShadow: "0 1px 4px rgba(0,0,0,0.06)",
      opacity: inStock ? 1 : 0.6,
      position: "relative",
      cursor: "default",
    }}>
      
      {/* Out of Stock Overlay */}
      {!inStock && (
        <div style={{
          position: "absolute",
          top: "50%",
          left: "50%",
          transform: "translate(-50%, -50%)",
          background: "#dc2626",
          color: "#fff",
          padding: "4px 12px",
          borderRadius: "4px",
          fontSize: "0.7rem",
          fontWeight: 900,
          letterSpacing: "0.08em",
          textTransform: "uppercase",
          zIndex: 10,
          boxShadow: "0 2px 8px rgba(220,38,38,0.4)",
        }}>
          Out of Stock
        </div>
      )}
      
      {/* Image — left */}
      <div style={{ position: "relative", width: "110px", minWidth: "110px", height: "110px", background: "#f5f5f5", flexShrink: 0, overflow: "hidden" }}>
        {/* Skeleton loader while image loads */}
        {isVisible && !imgLoaded && !imgError && (
          <div style={{ 
            position: "absolute", inset: 0, background: "linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%)",
            backgroundSize: "200% 100%", animation: "shimmer 1.5s infinite"
          }} />
        )}
        
        {isVisible && src && !imgError ? (
          <Image 
            src={src} 
            alt={item.title} 
            fill
            sizes="110px" 
            style={{ objectFit: "cover", opacity: imgLoaded ? 1 : 0, transition: "opacity 0.3s ease-in-out" }}
            onLoad={() => setImgLoaded(true)}
            onError={() => setImgError(true)} 
            loading="lazy"
            unoptimized 
          />
        ) : !isVisible || imgError ? (
          <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center" }}>
            <span style={{ fontWeight: 900, color: "#ccc", fontSize: "2rem" }}>{item.title.charAt(0)}</span>
          </div>
        ) : null}
        
        {item.tag && (
          <span style={{ position: "absolute", top: "5px", left: "5px", background: "#000", color: "#fff", fontSize: "0.52rem", fontWeight: 800, letterSpacing: "0.06em", textTransform: "uppercase", padding: "2px 5px", borderRadius: "2px", zIndex: 1 }}>
            {item.tag}
          </span>
        )}
        {hasDiscount && (
          <span style={{ position: "absolute", top: "5px", right: "5px", background: "#dc2626", color: "#fff", fontSize: "0.6rem", fontWeight: 900, letterSpacing: "0.04em", textTransform: "uppercase", padding: "3px 7px", borderRadius: "12px", boxShadow: "0 2px 6px rgba(220,38,38,0.4)", zIndex: 1 }}>
            {item.discount_percent}% OFF
          </span>
        )}
      </div>

      {/* Content — right */}
      <div style={{ padding: "0.65rem 0.85rem", flex: 1, display: "flex", flexDirection: "column", justifyContent: "space-between", minWidth: 0 }}>
        <div>
          <h3 style={{ fontWeight: 800, fontSize: "0.88rem", color: "#000", margin: "0 0 3px 0" }}>
            {item.title}
          </h3>
          <p style={{ color: "#888", fontSize: "0.70rem", lineHeight: 1.4, margin: 0,
            display: "-webkit-box", WebkitLineClamp: 2, WebkitBoxOrient: "vertical", overflow: "hidden",
            textOverflow: "ellipsis",
          } as React.CSSProperties}>
            {item.description}
          </p>
        </div>
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginTop: "8px" }}>
          <div style={{ display: "flex", alignItems: "center", gap: "6px" }}>
            {hasDiscount ? (
              <>
                <span style={{ fontWeight: 900, fontSize: "1rem", color: "#dc2626" }}>
                  Rs. {displayPrice.toLocaleString()}
                </span>
                <span style={{ fontWeight: 600, fontSize: "0.75rem", color: "#999", textDecoration: "line-through" }}>
                  Rs. {originalPrice.toLocaleString()}
                </span>
              </>
            ) : (
              <span style={{ fontWeight: 900, fontSize: "0.92rem", color: "#e05c00" }}>
                Rs. {displayPrice.toLocaleString()}
              </span>
            )}
          </div>
          <button
            onClick={(e) => { e.stopPropagation(); handleAdd(); }}
            disabled={!inStock}
            aria-label={`Add ${item.title} to cart`}
            style={{
              background: !inStock ? "#d1d5db" : (added ? "#16a34a" : "#1a1a1a"),
              color: "#fff", border: "none",
              padding: "7px 14px", fontSize: "0.72rem", fontWeight: 700,
              textTransform: "uppercase", letterSpacing: "0.04em",
              cursor: inStock ? "pointer" : "not-allowed", 
              display: "flex", alignItems: "center", gap: "4px",
              borderRadius: "6px", transition: "background 0.2s",
              opacity: inStock ? 1 : 0.6,
            }}
          >
            {!inStock
              ? <>Unavailable</>
              : (added
                ? <><ShoppingCart className="w-3.5 h-3.5" /> Added!</>
                : <><Plus className="w-3.5 h-3.5" /> Add to Cart</>
              )
            }
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Skeleton loader ───────────────────────────────────────
function CardSkeleton() {
  return (
    <div style={{ display: "flex", flexDirection: "row", border: "1px solid rgba(0,0,0,0.08)", borderRadius: "10px", overflow: "hidden", background: "#fff" }} className="animate-pulse">
      <div style={{ width: "110px", minWidth: "110px", height: "110px", background: "#f0f0f0", flexShrink: 0 }} />
      <div style={{ padding: "0.75rem", flex: 1, display: "flex", flexDirection: "column", gap: "8px", justifyContent: "center" }}>
        <div style={{ height: "13px", background: "#f0f0f0", borderRadius: "3px", width: "65%" }} />
        <div style={{ height: "10px", background: "#f0f0f0", borderRadius: "3px", width: "90%" }} />
        <div style={{ height: "10px", background: "#f0f0f0", borderRadius: "3px", width: "50%" }} />
      </div>
    </div>
  );
}

// ── Order status config ───────────────────────────────────
type OrderStatus = "pending" | "confirmed" | "dispatched" | "cancelled";

interface StatusCfg {
  icon: React.ReactNode;
  title: string;
  sub: string;
  note: string;
  color: string;
}

const STATUS_CONFIG: Record<OrderStatus, StatusCfg> = {
  pending: {
    icon: <Clock className="w-7 h-7" />,
    title: "Order Received!",
    sub: "We've received your order and will confirm it shortly.",
    note: "Keep your phone nearby — our team will confirm shortly.",
    color: "#e67e00",
  },
  confirmed: {
    icon: <ChefHat className="w-7 h-7" />,
    title: "Preparation Started!",
    sub: "Your order is being freshly prepared.",
    note: "Almost ready — it'll be on its way soon!",
    color: "#2563eb",
  },
  dispatched: {
    icon: <Bike className="w-7 h-7" />,
    title: "Out for Delivery!",
    sub: "Your order is on the way.",
    note: "Expected delivery in 30–45 minutes. Get ready!",
    color: "#16a34a",
  },
  cancelled: {
    icon: <XCircle className="w-7 h-7" />,
    title: "Order Cancelled",
    sub: "Your order was cancelled.",
    note: "Please contact us if you have any questions.",
    color: "#dc2626",
  },
};

// ── Cart Drawer ───────────────────────────────────────────
type DrawerView = "cart" | "checkout" | "success" | "login";

function CartDrawer({ cart, addToCart, removeFromCart, updateQty, clearCart, totalItems, totalPrice, onClose }:
  CartActions & { onClose: () => void }) {

  const [view, setView] = useState<DrawerView>("cart");
  const [isLoggedIn, setIsLoggedIn] = useState(false);

  useEffect(() => {
    try {
      const u = JSON.parse(localStorage.getItem("sz_user") ?? "{}");
      setIsLoggedIn(!!u?.id);
    } catch { setIsLoggedIn(false); }
  }, []);

  const [form, setForm] = useState({ name: "", phone: "", address: "", note: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [orderId, setOrderId] = useState<number | null>(null);
  const [orderStatus, setOrderStatus] = useState<OrderStatus>("pending");
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Auto-fill form if user is logged in
  useEffect(() => {
    try {
      const raw = localStorage.getItem("sz_user");
      if (raw) {
        const u = JSON.parse(raw);
        setForm(f => ({
          ...f,
          name:  f.name  || u.name  || "",
          phone: f.phone || u.phone || "",
        }));
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

    // Require login before placing order
    const loggedUser = (() => {
      try { const r = localStorage.getItem("sz_user"); return r ? JSON.parse(r) : null; } catch { return null; }
    })();
    if (!loggedUser?.id) {
      // Save cart so it's restored after login
      localStorage.setItem("sz_cart_pending", JSON.stringify(cart));
      window.location.href = "/login/";
      return;
    }

    setSubmitting(true);

    const cartPayload = cart.map(i => ({
      id: i.id, title: i.title, price: i.price, qty: i.qty
    }));

    const body = new URLSearchParams({
      name:    form.name,
      phone:   form.phone,
      address: form.address,
      notes:   form.note,
      items:   JSON.stringify(cartPayload),
      total:   String(totalPrice),
      user_id: String(loggedUser.id),
    });

    try {
      const res = await fetch(`${API_BASE}/save_order.php`, {
        method: "POST", body,
      });
      const data = await res.json();
      if (data.success) {
        setOrderId(data.order_id);
        setView("success");
        clearCart();
      } else {
        setErrors({ submit: data.message || "Could not place order. Try again." });
      }
    } catch {
      setErrors({ submit: "Network error. Please try again." });
    } finally {
      setSubmitting(false);
    }
  };

  const inputCls = (field: string) =>
    `cart-input${errors[field] ? " cart-input-err" : ""}`;

  // ── Poll order status every 10s after order placed ───────
  useEffect(() => {
    if (view !== "success" || !orderId) return;

    const poll = async () => {
      try {
        const res  = await fetch(`${API_BASE}/get_order_status.php?id=${orderId}`, { cache: "no-store" });
        const data = await res.json();
        if (data.success && data.status) {
          setOrderStatus(data.status as OrderStatus);
          // Stop polling once final state reached
          if (data.status === "dispatched" || data.status === "cancelled") {
            if (pollRef.current) clearInterval(pollRef.current);
          }
        }
      } catch { /* silent — keep polling */ }
    };

    poll(); // immediate first check
    pollRef.current = setInterval(poll, 10_000); // then every 10s

    return () => {
      if (pollRef.current) clearInterval(pollRef.current);
    };
  }, [view, orderId]);

  return (
    <>
      {/* Overlay */}
      <div className="cart-overlay" onClick={onClose} aria-hidden="true" />

      {/* Drawer */}
      <div className="cart-drawer" role="dialog" aria-label="Cart">

        {/* ── Cart view ── */}
        {view === "cart" && (
          <>
            <div className="cart-drawer-head">
              <div>
                <h2 className="cart-drawer-title">Your Cart</h2>
                <p className="cart-drawer-sub">{totalItems} item{totalItems !== 1 ? "s" : ""}</p>
              </div>
              <button className="cart-close-btn" onClick={onClose} aria-label="Close cart">
                <X className="w-5 h-5" />
              </button>
            </div>

            {cart.length === 0 ? (
              <div className="cart-empty">
                <ShoppingCart className="cart-empty-icon" />
                <p>Your cart is empty</p>
                <span>Add items from the menu</span>
              </div>
            ) : (
              <>
                <div className="cart-items">
                  {cart.map(item => (
                    <div key={item.id} className="cart-item">
                      <div className="cart-item-info">
                        <p className="cart-item-name">{item.title}</p>
                        <p className="cart-item-price">Rs. {item.price.toLocaleString()}</p>
                      </div>
                      <div className="cart-item-controls">
                        <button className="cart-qty-btn"
                          onClick={() => updateQty(item.id, item.qty - 1)}
                          aria-label="Decrease quantity">
                          <Minus className="w-3 h-3" />
                        </button>
                        <span className="cart-qty-num">{item.qty}</span>
                        <button className="cart-qty-btn"
                          onClick={() => updateQty(item.id, item.qty + 1)}
                          aria-label="Increase quantity">
                          <Plus className="w-3 h-3" />
                        </button>
                        <button className="cart-del-btn"
                          onClick={() => removeFromCart(item.id)}
                          aria-label={`Remove ${item.title}`}>
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="cart-footer">
                  <div className="cart-total-row">
                    <span className="cart-total-label">Total</span>
                    <span className="cart-total-price">Rs. {totalPrice.toLocaleString()}</span>
                  </div>
                  <button className="cart-checkout-btn" onClick={() => setView("checkout")}>
                    Proceed to Checkout
                  </button>
                  <button className="cart-clear-btn" onClick={clearCart}>
                    Clear Cart
                  </button>
                </div>
              </>
            )}
          </>
        )}

        {/* ── Login prompt view ── */}
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
              <h2 className="cart-success-title" style={{ fontSize: "1.1rem" }}>
                Almost There!
              </h2>
              <p className="cart-success-sub">
                Your cart has {totalItems} item{totalItems !== 1 ? "s" : ""} — Rs. {totalPrice.toLocaleString()}
              </p>
              <p className="cart-success-note">
                Login or create an account to complete your order. Your cart will be saved.
              </p>

              <a
                href="/login/"
                onClick={() => localStorage.setItem("sz_cart_pending", JSON.stringify(cart))}
                className="cart-checkout-btn"
                style={{ width: "100%", textDecoration: "none", justifyContent: "center", marginTop: "0.5rem" }}
              >
                Login to Continue
              </a>
              <a
                href="/register/"
                onClick={() => localStorage.setItem("sz_cart_pending", JSON.stringify(cart))}
                style={{
                  width: "100%", textAlign: "center", padding: "0.75rem",
                  border: "1px solid rgba(0,0,0,0.15)", fontSize: "0.75rem",
                  fontWeight: 700, textTransform: "uppercase", letterSpacing: "0.08em",
                  color: "#000", textDecoration: "none", display: "block"
                }}
              >
                Create Account
              </a>
              <button
                onClick={() => setView("cart")}
                className="cart-clear-btn"
                style={{ marginTop: "0.5rem" }}
              >
                ← Back to Cart
              </button>
            </div>
          </>
        )}

        {/* ── Checkout view ── */}
        {view === "checkout" && (
          <>
            <div className="cart-drawer-head">
              <div>
                <h2 className="cart-drawer-title">Place Order</h2>
                <p className="cart-drawer-sub">Rs. {totalPrice.toLocaleString()} · {totalItems} item{totalItems !== 1 ? "s" : ""}</p>
              </div>
              <button className="cart-close-btn" onClick={() => setView("cart")} aria-label="Back to cart">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="cart-form" noValidate>
              <div className="cart-form-group">
                <label className="cart-label">Full Name *</label>
                <input className={inputCls("name")} value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  placeholder="Your name" />
                {errors.name && <span className="cart-err">{errors.name}</span>}
              </div>

              <div className="cart-form-group">
                <label className="cart-label">Phone Number *</label>
                <input className={inputCls("phone")} value={form.phone}
                  onChange={e => setForm(f => ({ ...f, phone: e.target.value }))}
                  placeholder="03XX XXXXXXX" type="tel" />
                {errors.phone && <span className="cart-err">{errors.phone}</span>}
              </div>

              <div className="cart-form-group">
                <label className="cart-label">Delivery Address *</label>
                <textarea className={inputCls("address")} value={form.address}
                  onChange={e => setForm(f => ({ ...f, address: e.target.value }))}
                  placeholder="House No, Street, Area, City" rows={3} />
                {errors.address && <span className="cart-err">{errors.address}</span>}
              </div>

              <div className="cart-form-group">
                <label className="cart-label">Note <span style={{ color: "#aaa", fontWeight: 400 }}>(optional)</span></label>
                <input className="cart-input" value={form.note}
                  onChange={e => setForm(f => ({ ...f, note: e.target.value }))}
                  placeholder="e.g. Extra sauce, ring bell" />
              </div>

              {/* Order summary */}
              <div className="cart-summary">
                {cart.map(i => (
                  <div key={i.id} className="cart-summary-row">
                    <span>{i.title} × {i.qty}</span>
                    <span>Rs. {(i.price * i.qty).toLocaleString()}</span>
                  </div>
                ))}
                <div className="cart-summary-total">
                  <span>Total</span>
                  <span>Rs. {totalPrice.toLocaleString()}</span>
                </div>
              </div>

              {errors.submit && (
                <p className="cart-err" style={{ marginBottom: "0.8rem" }}>{errors.submit}</p>
              )}

              <button type="submit" className="cart-checkout-btn" disabled={submitting}>
                {submitting
                  ? <><Loader2 className="w-4 h-4 animate-spin" /> Placing Order…</>
                  : <><CheckCircle className="w-4 h-4" /> Confirm Order</>}
              </button>
            </form>
          </>
        )}

        {/* ── Success view ── */}
        {view === "success" && (() => {
          const cfg = STATUS_CONFIG[orderStatus] ?? STATUS_CONFIG.pending;
          const isDone = orderStatus === "dispatched" || orderStatus === "cancelled";
          return (
            <div className="cart-success">
              {/* Animated status icon */}
              <div className="cart-success-icon" style={{ background: cfg.color, color: "#fff" }}>
                {cfg.icon}
              </div>

              {/* Status title */}
              <h2 className="cart-success-title">{cfg.title}</h2>

              {/* Status sub */}
              <p className="cart-success-sub">{cfg.sub}</p>

              {/* Note */}
              <p className="cart-success-note">{cfg.note}</p>

              {/* Live status tracker bar */}
              <div className="cart-status-track">
                {(["pending", "confirmed", "dispatched"] as OrderStatus[]).map((s, i) => {
                  const steps = ["pending", "confirmed", "dispatched"] as OrderStatus[];
                  const currentIdx = steps.indexOf(orderStatus === "cancelled" ? "pending" : orderStatus);
                  const isActive   = i <= currentIdx && orderStatus !== "cancelled";
                  return (
                    <div key={s} className="cart-status-step">
                      <div className={`cart-status-dot ${isActive ? "active" : ""}`}
                           style={isActive ? { background: cfg.color } : {}}>
                        {i === 0 ? "1" : i === 1 ? "2" : "3"}
                      </div>
                      <span className={`cart-status-label ${isActive ? "active" : ""}`}>
                        {i === 0 ? "Received" : i === 1 ? "Preparation" : "Delivery"}
                      </span>
                      {i < 2 && <div className={`cart-status-line ${i < currentIdx && orderStatus !== "cancelled" ? "active" : ""}`}
                                      style={i < currentIdx && orderStatus !== "cancelled" ? { background: cfg.color } : {}} />}
                    </div>
                  );
                })}
              </div>

              {/* Polling indicator */}
              {!isDone && (
                <p className="cart-polling-note">
                  <span className="cart-polling-dot" /> Tracking your order live...
                </p>
              )}

              <button className="cart-checkout-btn" style={{ marginTop: "1rem" }} onClick={onClose}>
                Continue Browsing
              </button>
            </div>
          );
        })()}
      </div>
    </>
  );
}

// ── Main page ─────────────────────────────────────────────
export default function MenuPage() {
  const [items, setItems]               = useState<MenuItem[]>([]);
  const [categories, setCategories]     = useState<{ id: string; label: string; count: number }[]>([]);
  const [activeCategory, setActiveCategory] = useState("");
  const [search, setSearch]             = useState("");
  const [loading, setLoading]           = useState(true);
  const [error, setError]               = useState("");

  // Cart state
  const [cart, setCart]           = useState<CartItem[]>([]);
  const [cartOpen, setCartOpen]   = useState(false);
  const [cartFlash, setCartFlash] = useState(false);

  const totalItems = cart.reduce((s, i) => s + i.qty, 0);
  const totalPrice = cart.reduce((s, i) => s + i.price * i.qty, 0);

  const addToCart = useCallback((item: MenuItem) => {
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
        qty: 1 
      }];
    });
    setCartFlash(true);
    setTimeout(() => setCartFlash(false), 800);
  }, []);

  const removeFromCart = useCallback((id: number) => {
    setCart(prev => prev.filter(i => i.id !== id));
  }, []);

  const updateQty = useCallback((id: number, qty: number) => {
    if (qty <= 0) { removeFromCart(id); return; }
    setCart(prev => prev.map(i => i.id === id ? { ...i, qty } : i));
  }, [removeFromCart]);

  const clearCart = useCallback(() => setCart([]), []);

  // Fetch menu items
  useEffect(() => {
    setLoading(true);
    // Add timestamp to prevent caching
    const timestamp = new Date().getTime();
    fetch(`${API_BASE}/get_menu.php?t=${timestamp}`, { 
      cache: "no-store",
      headers: {
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'Expires': '0'
      }
    })
      .then(r => r.json())
      .then((data: { success: boolean; items: MenuItem[] }) => {
        if (!data.success) throw new Error("API failure");
        // Normalise: API returns id as string, cast to number
        const fetched = data.items.map(item => ({
          ...item,
          id:    Number(item.id),
          price: Number(item.price),
          in_stock: item.in_stock !== false, // boolean only
        }));
        console.log('Fetched items with stock status:', fetched); // Debug log
        setItems(fetched);
        const catMap = new Map<string, number>();
        fetched.forEach(item => catMap.set(item.category, (catMap.get(item.category) ?? 0) + 1));
        const cats = Array.from(catMap.entries()).map(([id, count]) => ({
          id, count,
          label: id.charAt(0).toUpperCase() + id.slice(1).replace(/_/g, " "),
        }));
        setCategories(cats);
        if (cats.length > 0) setActiveCategory(cats[0].id);
      })
      .catch(() => setError("Could not load menu. Please try again."))
      .finally(() => setLoading(false));
  }, []);

  // Lock body scroll when cart open
  useEffect(() => {
    document.body.style.overflow = cartOpen ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [cartOpen]);

  const filtered = useMemo(() => {
    if (search.trim()) {
      return items.filter(i =>
        i.title.toLowerCase().includes(search.toLowerCase()) ||
        i.description.toLowerCase().includes(search.toLowerCase())
      );
    }
    return items.filter(i => i.category === activeCategory);
  }, [items, activeCategory, search]);

  const currentLabel = search.trim()
    ? `Results for "${search}"`
    : categories.find(c => c.id === activeCategory)?.label ?? "Menu";

  return (
    <div className="menu-page-root">

      {/* Heading */}
      <div className="menu-heading-bar">
        <div className="custom-container">
          <p className="text-xs font-bold tracking-widest text-white/40 uppercase mb-1">
            Fresh Ingredients, Bold Flavours
          </p>
          <h1 className="menu-page-title">Our Menu</h1>
        </div>
      </div>

      {/* Search */}
      <div className="menu-search-bar-wrap">
        <div className="custom-container">
          <div className="menu-search-inner">
            <Search className="menu-search-icon" />
            <input type="search" value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Search your favourites…"
              className="menu-search-input" aria-label="Search menu" />
          </div>
        </div>
      </div>

      <div className="custom-container menu-layout">
        {/* Sidebar */}
        <aside className="menu-sidebar">
          <div className="menu-sidebar-inner">
            <p className="menu-sidebar-heading">Categories</p>
            {loading ? (
              <div style={{ display: "flex", flexDirection: "column", gap: "6px", padding: "8px" }}>
                {[...Array(6)].map((_, i) => (
                  <div key={i} style={{ height: "36px", background: "#f0f0f0" }} className="animate-pulse" />
                ))}
              </div>
            ) : (
              <ul className="menu-cat-list">
                {categories.map(cat => (
                  <li key={cat.id}>
                    <button
                      onClick={() => { setActiveCategory(cat.id); setSearch(""); }}
                      className={`menu-cat-btn ${activeCategory === cat.id && !search ? "active" : ""}`}
                    >
                      <span className="menu-cat-label">{cat.label}</span>
                      <span className="menu-cat-count">{cat.count}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </aside>

        {/* Content */}
        <div className="menu-content">
          <div className="menu-section-head">
            <div className="menu-section-head-box">
              <h2 className="menu-section-title">{currentLabel}</h2>
              {!loading && (
                <span className="menu-section-count">
                  {filtered.length} item{filtered.length !== 1 ? "s" : ""}
                </span>
              )}
            </div>
          </div>

          {error && (
            <div style={{ padding: "1.5rem", border: "1px solid rgba(0,0,0,0.15)", textAlign: "center", color: "#888", fontSize: "0.88rem" }}>
              {error}
            </div>
          )}

          {loading && !error && (
            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
              {[...Array(6)].map((_, i) => <CardSkeleton key={i} />)}
            </div>
          )}

          {!loading && !error && items.length === 0 && (
            <div className="menu-empty">
              <Search className="menu-empty-icon" />
              <p>Menu items coming soon. Check back shortly!</p>
            </div>
          )}

          {!loading && !error && items.length > 0 && filtered.length > 0 && (
            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
              {filtered.map(item => (
                <ProductCard key={item.id} item={item} onAdd={addToCart} />
              ))}
            </div>
          )}

          {!loading && !error && items.length > 0 && filtered.length === 0 && (
            <div className="menu-empty">
              <Search className="menu-empty-icon" />
              <p>No items found for &ldquo;{search}&rdquo;</p>
            </div>
          )}
        </div>
      </div>

      {/* Mobile category tabs */}
      {!loading && (
        <div className="menu-mobile-tabs">
          {categories.map(cat => (
            <button key={cat.id}
              onClick={() => { setActiveCategory(cat.id); setSearch(""); }}
              className={`menu-mobile-tab ${activeCategory === cat.id && !search ? "active" : ""}`}
            >
              {cat.label}
            </button>
          ))}
        </div>
      )}

      {/* CTA */}
      <section className="menu-cta-section">
        <div className="custom-container menu-cta-inner">
          <div>
            <h2 className="menu-cta-title">Ready to Order?</h2>
            <p className="menu-cta-sub">Walk in or call us — open 3pm daily, Satellite Town, Rawalpindi.</p>
          </div>
          <div className="menu-cta-actions">
            <span className="btn-white inline-flex items-center gap-2 cursor-default">
              <Phone className="w-4 h-4" /> Contact via WhatsApp
            </span>
            <a href="https://maps.google.com/?q=6th+Road+opposite+Noor+Mobile+Plaza+Satellite+Town+Rawalpindi"
              target="_blank" rel="noopener noreferrer" className="menu-cta-directions">
              Get Directions <ChevronRight className="w-4 h-4" />
            </a>
          </div>
        </div>
      </section>

      {/* ── Floating Cart Button ── */}
      {totalItems > 0 && (
        <button
          className={`cart-fab ${cartFlash ? "cart-fab-flash" : ""}`}
          onClick={() => setCartOpen(true)}
          aria-label={`Open cart — ${totalItems} items`}
        >
          <ShoppingCart className="w-5 h-5" />
          <span className="cart-fab-label">
            {totalItems} item{totalItems !== 1 ? "s" : ""} · Rs. {totalPrice.toLocaleString()}
          </span>
          <span className="cart-fab-badge">{totalItems}</span>
        </button>
      )}

      {/* ── Cart Drawer ── */}
      {cartOpen && (
        <CartDrawer
          cart={cart}
          addToCart={addToCart}
          removeFromCart={removeFromCart}
          updateQty={updateQty}
          clearCart={clearCart}
          totalItems={totalItems}
          totalPrice={totalPrice}
          onClose={() => setCartOpen(false)}
        />
      )}
    </div>
  );
}
