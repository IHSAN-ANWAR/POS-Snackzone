"use client";

"use client";
import { useState, useEffect, useMemo, useRef, useCallback } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import {
  ShoppingCart, Plus, Minus, Trash2,
  Phone, MapPin, User, Loader2,
  CheckCircle, ArrowRight, Lock,
  MessageCircle, X, Send, ChevronDown,
} from "lucide-react";
import { API_BASE, postForm, getJSON } from "@/lib/api";

interface MenuItem {
  id: number;
  title: string;
  category: string;
  price: number;
}

interface CartItem extends MenuItem { qty: number }

function FieldError({ msg }: { msg?: string }) {
  if (!msg) return null;
  return <p className="text-[#e63946] text-xs mt-1.5 font-medium">{msg}</p>;
}

export default function OrderPage() {
  const router = useRouter();
  const [authChecked, setAuthChecked] = useState(false);
  const [isLoggedIn, setIsLoggedIn]   = useState(false);

  const [menu, setMenu]         = useState<MenuItem[]>([]);
  const [menuLoading, setMenuLoading] = useState(true);
  const [cart, setCart]         = useState<CartItem[]>([]);
  const [activeTab, setActiveTab] = useState("");
  const [step, setStep]         = useState<"menu" | "details" | "done">("menu");

  const [form, setForm] = useState({ name: "", phone: "", phone2: "", address: "", notes: "" });
  const [touched, setTouched]   = useState<Record<string, boolean>>({});
  const [errors, setErrors]     = useState<Record<string, string>>({});
  const [placing, setPlacing]   = useState(false);

  // ── Auth guard ──────────────────────────────────────
  useEffect(() => {
    try {
      const u = JSON.parse(localStorage.getItem("sz_user") ?? "{}");
      if (u?.id) {
        setIsLoggedIn(true);
      }
    } catch { /* ignore */ }
    setAuthChecked(true);
  }, []);

  const [serverErr, setServerErr] = useState("");
  const [orderId, setOrderId]   = useState(0);

  // Fetch menu from backend
  useEffect(() => {
    getJSON<{ success: boolean; items: MenuItem[] }>("get_menu.php")
      .then(({ items }) => {
        // Normalise: API returns id as string, cast to number
        const normalised = items.map(item => ({
          ...item,
          id:    Number(item.id),
          price: Number(item.price),
        }));
        setMenu(normalised);
        if (normalised.length > 0) setActiveTab(normalised[0].category);
      })
      .catch(() => {/* show empty state */})
      .finally(() => setMenuLoading(false));
  }, []);

  // Pre-fill from registered user
  useEffect(() => {
    try {
      const u = JSON.parse(localStorage.getItem("sz_user") ?? "{}");
      if (u?.name || u?.phone) setForm(f => ({ ...f, name: u.name ?? "", phone: u.phone ?? "" }));
    } catch { /* ignore */ }
  }, []);

  const categories = useMemo(() => [...new Set(menu.map(i => i.category))], [menu]);
  const filtered   = menu.filter(i => i.category === activeTab);
  const total      = cart.reduce((s, i) => s + i.price * i.qty, 0);

  const addItem = (item: MenuItem) =>
    setCart(c => {
      const ex = c.find(x => x.id === item.id);
      return ex
        ? c.map(x => x.id === item.id ? { ...x, qty: x.qty + 1 } : x)
        : [...c, { ...item, qty: 1 }];
    });

  const changeQty = (id: number, delta: number) =>
    setCart(c => c.flatMap(x => {
      if (x.id !== id) return [x];
      const q = x.qty + delta;
      return q > 0 ? [{ ...x, qty: q }] : [];
    }));

  const validate = (f: typeof form) => {
    const e: Record<string, string> = {};
    if (!f.name.trim())    e.name    = "Name is required.";
    if (!f.phone.trim())   e.phone   = "Phone number is required.";
    else if (!/^[0-9+\s\-]{7,15}$/.test(f.phone.trim())) e.phone = "Enter a valid phone number.";
    if (f.phone2.trim() && !/^[0-9+\s\-]{7,15}$/.test(f.phone2.trim())) e.phone2 = "Enter a valid number.";
    if (!f.address.trim()) e.address = "Delivery address is required.";
    return e;
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const updated = { ...form, [e.target.name]: e.target.value };
    setForm(updated);
    if (touched[e.target.name]) setErrors(validate(updated));
  };

  const handleBlur = (e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setTouched(t => ({ ...t, [e.target.name]: true }));
    setErrors(validate(form));
  };

  const handleDetailsSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerErr("");
    setTouched(Object.fromEntries(Object.keys(form).map(k => [k, true])));
    const errs = validate(form);
    setErrors(errs);
    if (Object.keys(errs).length > 0) return;

    setPlacing(true);
    try {
      const user = JSON.parse(localStorage.getItem("sz_user") ?? "{}");
      const res = await postForm("save_order.php", {
        user_id: user?.id ?? 0,
        name:    form.name,
        phone:   form.phone,
        phone2:  form.phone2,
        address: form.address,
        items:   JSON.stringify(cart.map(i => ({ id: i.id, title: i.title, price: i.price, qty: i.qty }))),
        total,
        notes:   form.notes,
      }) as { success: boolean; order_id?: number; message?: string };

      if (res.success) { setOrderId(res.order_id ?? 0); setStep("done"); }
      else setServerErr(res.message ?? "Could not place order. Please try again.");
    } catch {
      setServerErr("Could not reach server. Please check your connection and try again.");
    } finally {
      setPlacing(false);
    }
  };

  /* ── AUTH WALL ──────────────────────────────────────── */
  if (!authChecked) {
    // Still checking localStorage — show nothing to avoid flash
    return null;
  }

  if (!isLoggedIn) {
    return (
      <div className="bg-white min-h-[80vh] flex items-center justify-center px-5">
        <div className="text-center" style={{ maxWidth: "380px" }}>
          <div className="w-16 h-16 bg-black flex items-center justify-center mx-auto mb-5">
            <Lock className="w-8 h-8 text-white" />
          </div>
          <h1 className="font-black text-black mb-2" style={{ fontSize: "1.8rem", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
            Login Required
          </h1>
          <p className="text-[#555] text-sm mb-6">
            Please login or create an account to place your order.
          </p>
          <div className="flex flex-col gap-3">
            <Link href="/login" className="btn-black w-full justify-center py-3 flex items-center gap-2">
              <User className="w-4 h-4" /> Login to Your Account
            </Link>
            <Link href="/register" className="btn-white w-full justify-center py-3 flex items-center gap-2" style={{ border: "2px solid black" }}>
              Create Account
            </Link>
            <Link href="/menu" className="text-xs text-[#888] hover:text-black transition-colors mt-1">
              ← Back to Menu
            </Link>
          </div>
        </div>
      </div>
    );
  }

  /* ── STEP: MENU ─────────────────────────────────────── */
  if (step === "menu") return (
    <div className="bg-white">
      <div className="bg-black py-6 px-5">
        <div className="custom-container flex items-center justify-between flex-wrap gap-3">
          <div>
            <p className="text-xs font-bold tracking-widest text-white/50 uppercase mb-1">Online Order</p>
            <h1 className="font-black text-white leading-none"
              style={{ fontSize: "clamp(2rem,6vw,4rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
              Place Order
            </h1>
          </div>
          {cart.length > 0 && (
            <button onClick={() => setStep("details")} className="btn-white flex items-center gap-2 text-sm">
              <ShoppingCart className="w-4 h-4" />
              Checkout ({cart.reduce((s, i) => s + i.qty, 0)} items) — Rs. {total.toLocaleString()}
            </button>
          )}
        </div>
      </div>

      <div className="custom-container py-8 px-5">
        {/* Category tabs */}
        <div className="flex gap-2 overflow-x-auto pb-2 mb-6" style={{ scrollbarWidth: "none" }}>
          {categories.map(cat => (
            <button key={cat} onClick={() => setActiveTab(cat)}
              className={`shrink-0 px-4 py-2 text-xs font-bold uppercase tracking-wide border transition-all ${
                activeTab === cat
                  ? "bg-black text-white border-black"
                  : "bg-white text-[#555] border-black/15 hover:border-black/40"
              }`}>
              {cat}
            </button>
          ))}
        </div>

        {/* Loading */}
        {menuLoading && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="animate-pulse p-4" style={{ border: "1px solid rgba(0,0,0,0.1)" }}>
                <div style={{ height: "14px", background: "#f0f0f0", width: "60%", marginBottom: "8px" }} />
                <div style={{ height: "10px", background: "#f0f0f0", width: "30%" }} />
              </div>
            ))}
          </div>
        )}

        {/* Items */}
        {!menuLoading && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
            {filtered.map(item => {
              const inCart = cart.find(c => c.id === item.id);
              return (
                <div key={item.id} className="p-4 flex items-center justify-between gap-4"
                  style={{ border: "1px solid rgba(0,0,0,0.1)" }}>
                  <div className="flex-1 min-w-0">
                    <p className="font-black text-black text-sm uppercase">{item.title}</p>
                    <p className="font-black text-black text-base mt-1">Rs. {item.price.toLocaleString()}</p>
                  </div>
                  {inCart ? (
                    <div className="flex items-center gap-2 shrink-0">
                      <button onClick={() => changeQty(item.id, -1)}
                        className="w-8 h-8 bg-[#f0f0f0] flex items-center justify-center hover:bg-[#e0e0e0]">
                        <Minus className="w-3.5 h-3.5" />
                      </button>
                      <span className="font-black text-black w-5 text-center text-sm">{inCart.qty}</span>
                      <button onClick={() => changeQty(item.id, 1)}
                        className="w-8 h-8 bg-black flex items-center justify-center hover:bg-[#333]">
                        <Plus className="w-3.5 h-3.5 text-white" />
                      </button>
                    </div>
                  ) : (
                    <button onClick={() => addItem(item)}
                      className="w-9 h-9 bg-black flex items-center justify-center shrink-0 hover:bg-[#333]">
                      <Plus className="w-4 h-4 text-white" />
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        )}

        {/* Empty */}
        {!menuLoading && menu.length === 0 && (
          <div className="text-center py-16 text-[#aaa] text-sm">
            Menu is being updated. Check back shortly.
          </div>
        )}

        {/* Sticky checkout bar */}
        {cart.length > 0 ? (
          <div className="sticky bottom-4">
            <div className="bg-black p-4 flex items-center justify-between gap-4 flex-wrap"
              style={{ boxShadow: "0 8px 30px rgba(0,0,0,0.2)" }}>
              <div>
                <p className="text-white/60 text-xs uppercase font-bold tracking-wide">Your Order</p>
                <p className="text-white font-black text-lg">Rs. {total.toLocaleString()}</p>
                <p className="text-white/50 text-xs">{cart.reduce((s, i) => s + i.qty, 0)} items</p>
              </div>
              <button onClick={() => setStep("details")} className="btn-white flex items-center gap-2 shrink-0">
                <ArrowRight className="w-4 h-4" /> Proceed to Checkout
              </button>
            </div>
          </div>
        ) : (
          !menuLoading && (
            <div className="text-center py-16">
              <ShoppingCart className="w-12 h-12 mx-auto mb-3 opacity-20" />
              <p className="text-sm text-[#aaa]">Add items from the menu above to start your order.</p>
            </div>
          )
        )}
      </div>
    </div>
  );

  /* ── STEP: DETAILS ──────────────────────────────────── */
  if (step === "details") return (
    <div className="bg-white">
      <div className="bg-black py-6 px-5">
        <div className="custom-container">
          <p className="text-xs font-bold tracking-widest text-white/50 uppercase mb-1">Step 2 of 2</p>
          <h1 className="font-black text-white leading-none"
            style={{ fontSize: "clamp(2rem,6vw,3rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
            Your Details
          </h1>
        </div>
      </div>

      <section className="py-10 px-5">
        <div className="custom-container">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start" style={{ maxWidth: "900px" }}>

            <div style={{ border: "1px solid rgba(0,0,0,0.1)", padding: "28px" }}>
              <form onSubmit={handleDetailsSubmit} noValidate className="space-y-4">
                <div>
                  <label className="contact-label"><User className="w-3 h-3" /> Your Name</label>
                  <input name="name" value={form.name} onChange={handleChange} onBlur={handleBlur}
                    placeholder="Full Name"
                    className={`contact-input${touched.name && errors.name ? " contact-input-error" : ""}`} />
                  <FieldError msg={touched.name ? errors.name : undefined} />
                </div>
                <div>
                  <label className="contact-label"><Phone className="w-3 h-3" /> Phone Number</label>
                  <input name="phone" value={form.phone} onChange={handleChange} onBlur={handleBlur}
                    placeholder="0310 0000000"
                    className={`contact-input${touched.phone && errors.phone ? " contact-input-error" : ""}`} />
                  <FieldError msg={touched.phone ? errors.phone : undefined} />
                </div>
                <div>
                  <label className="contact-label"><Phone className="w-3 h-3" /> Alternate Phone <span className="text-[#bbb] normal-case font-normal">(optional)</span></label>
                  <input name="phone2" value={form.phone2} onChange={handleChange} onBlur={handleBlur}
                    placeholder="0300 0000000"
                    className={`contact-input${touched.phone2 && errors.phone2 ? " contact-input-error" : ""}`} />
                  <FieldError msg={touched.phone2 ? errors.phone2 : undefined} />
                </div>
                <div>
                  <label className="contact-label"><MapPin className="w-3 h-3" /> Delivery Address</label>
                  <textarea name="address" value={form.address} onChange={handleChange} onBlur={handleBlur}
                    rows={3} placeholder="House #, Street #, Area, City…"
                    className={`contact-input resize-none${touched.address && errors.address ? " contact-input-error" : ""}`} />
                  <FieldError msg={touched.address ? errors.address : undefined} />
                </div>
                <div>
                  <label className="contact-label">Notes <span className="text-[#bbb] normal-case font-normal">(optional)</span></label>
                  <textarea name="notes" value={form.notes} onChange={handleChange}
                    rows={2} placeholder="Special instructions…" className="contact-input resize-none" />
                </div>

                {serverErr && (
                  <p className="text-[#e63946] text-xs font-medium bg-red-50 border border-red-100 px-3 py-2">
                    {serverErr}
                  </p>
                )}

                <button type="submit" disabled={placing}
                  className="btn-black w-full justify-center py-3 disabled:opacity-50 disabled:cursor-not-allowed">
                  {placing
                    ? <><Loader2 className="w-4 h-4 animate-spin" /> Placing order…</>
                    : <><CheckCircle className="w-4 h-4" /> Confirm Order</>}
                </button>
                <button type="button" onClick={() => setStep("menu")}
                  className="w-full text-center text-xs text-[#888] hover:text-black transition-colors">
                  ← Back to Menu
                </button>
              </form>
            </div>

            {/* Order summary */}
            <div style={{ border: "1px solid rgba(0,0,0,0.1)", overflow: "hidden" }}>
              <div className="bg-black px-5 py-3">
                <p className="text-white font-black text-sm uppercase tracking-wide">Order Summary</p>
              </div>
              <div className="p-4 space-y-2">
                {cart.map(item => (
                  <div key={item.id} className="flex items-center justify-between gap-3 py-2"
                    style={{ borderBottom: "1px solid rgba(0,0,0,0.06)" }}>
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <span className="w-6 h-6 bg-black text-white text-xs font-black flex items-center justify-center shrink-0">
                        {item.qty}
                      </span>
                      <span className="text-xs font-semibold text-black truncate">{item.title}</span>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      <span className="text-xs font-bold text-black">Rs. {(item.price * item.qty).toLocaleString()}</span>
                      <button onClick={() => changeQty(item.id, -item.qty)} className="text-[#ccc] hover:text-[#e63946] transition-colors">
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                ))}
                <div className="flex justify-between pt-3">
                  <span className="font-black text-black text-sm uppercase">Total</span>
                  <span className="font-black text-black text-base">Rs. {total.toLocaleString()}</span>
                </div>
              </div>
            </div>

          </div>
        </div>
      </section>
    </div>
  );

  /* ── STEP: DONE ─────────────────────────────────────── */
  return (
    <div className="bg-white min-h-[80vh]">
      <section className="py-20 px-5">
        <div className="custom-container text-center" style={{ maxWidth: "400px" }}>
          <div className="w-16 h-16 bg-black flex items-center justify-center mx-auto mb-5">
            <CheckCircle className="w-8 h-8 text-white" />
          </div>
          <h1 className="font-black text-black mb-2" style={{ fontSize: "1.8rem", textTransform: "uppercase" }}>
            Order Placed!
          </h1>
          <p className="text-[#555] text-sm mb-1">Order <strong>#{orderId}</strong> received.</p>
          <p className="text-[#888] text-sm mb-2">
            Delivering to: <strong className="text-black">{form.address}</strong>
          </p>
          <p className="text-[#888] text-xs mb-8">
            We&apos;ll call <strong className="text-black">{form.phone}</strong> to confirm.
          </p>
          <div className="flex flex-col gap-3">
            <span className="btn-black w-full justify-center py-3 flex items-center gap-2 cursor-default">
              <Phone className="w-4 h-4" /> Contact via WhatsApp
            </span>
            <Link href="/menu" className="text-xs text-[#888] hover:text-black transition-colors">
              Browse Menu Again
            </Link>
          </div>
        </div>
      </section>

      {/* Live Chat Widget */}
      <LiveChatWidget orderId={orderId} customerName={form.name} />
    </div>
  );
}

/* ═══════════════════════════════════════════════════════════
   LIVE CHAT WIDGET — shown after order is placed
   ═══════════════════════════════════════════════════════════ */
interface ChatMsg {
  id: number;
  sender: "user" | "receptionist";
  message: string;
  created_at: string;
}

function getOrCreateToken(orderId: number): string {
  const key = `sz_chat_token_${orderId}`;
  let token = localStorage.getItem(key);
  if (!token) {
    token = Array.from(crypto.getRandomValues(new Uint8Array(24)))
      .map(b => b.toString(16).padStart(2, "0"))
      .join("");
    localStorage.setItem(key, token);
  }
  return token;
}

function LiveChatWidget({ orderId, customerName }: { orderId: number; customerName: string }) {
  const [open, setOpen]           = useState(false);
  const [messages, setMessages]   = useState<ChatMsg[]>([]);
  const [input, setInput]         = useState("");
  const [sending, setSending]     = useState(false);
  const [unread, setUnread]       = useState(0);
  const [sessionId, setSessionId] = useState<number | null>(null);
  const [started, setStarted]     = useState(false); // first message sent
  const lastIdRef   = useRef(0);
  const pollRef     = useRef<ReturnType<typeof setTimeout> | null>(null);
  const bottomRef   = useRef<HTMLDivElement>(null);
  const inputRef    = useRef<HTMLInputElement>(null);

  const token = useRef<string>("");

  useEffect(() => {
    token.current = getOrCreateToken(orderId);
  }, [orderId]);

  // Scroll to bottom when messages change
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  // Focus input when chat opens
  useEffect(() => {
    if (open) {
      setTimeout(() => inputRef.current?.focus(), 100);
      setUnread(0);
    }
  }, [open]);

  // Poll for new messages every 4s
  const poll = useCallback(async () => {
    if (!orderId) return;
    try {
      const params: Record<string, string> = {
        action:      "get_messages",
        order_id:    String(orderId),
        guest_token: token.current,
        after_id:    String(lastIdRef.current),
      };
      const res = await getJSON<{
        success: boolean;
        messages: ChatMsg[];
        session_id?: number;
      }>("chat.php", params);

      if (res.success && res.messages.length > 0) {
        setMessages(prev => {
          const existingIds = new Set(prev.map(m => m.id));
          const newMsgs = res.messages.filter(m => !existingIds.has(m.id));
          if (newMsgs.length === 0) return prev;
          lastIdRef.current = Math.max(...res.messages.map(m => m.id));
          // Count new receptionist messages when chat is closed
          const newFromReceptionist = newMsgs.filter(m => m.sender === "receptionist").length;
          if (!open && newFromReceptionist > 0) {
            setUnread(u => u + newFromReceptionist);
          }
          return [...prev, ...newMsgs];
        });
        if (res.session_id) setSessionId(res.session_id);
      }
    } catch { /* network hiccup — ignore */ }
  }, [orderId, open]);

  useEffect(() => {
    if (!started) return; // don't poll until user sends first message
    poll();
    pollRef.current = setInterval(poll, 4000);
    return () => { if (pollRef.current) clearInterval(pollRef.current); };
  }, [poll, started]);

  const sendMessage = async () => {
    const msg = input.trim();
    if (!msg || sending) return;
    setSending(true);
    setInput("");

    // Optimistic add
    const tempId = Date.now();
    const optimistic: ChatMsg = { id: tempId, sender: "user", message: msg, created_at: new Date().toISOString() };
    setMessages(prev => [...prev, optimistic]);

    try {
      const res = await fetch(`${API_BASE}/chat.php`, {
        method: "POST",
        body: new URLSearchParams({
          action:      "send_message",
          order_id:    String(orderId),
          guest_token: token.current,
          message:     msg,
        }),
      });
      const data = await res.json();
      if (data.success) {
        // Replace optimistic with real
        setMessages(prev => prev.map(m =>
          m.id === tempId ? { ...m, id: data.id } : m
        ));
        lastIdRef.current = Math.max(lastIdRef.current, data.id);
        if (data.session_id) setSessionId(data.session_id);
        if (!started) setStarted(true);
      }
    } catch { /* ignore */ }
    setSending(false);
    // Start polling after first message
    if (!started) setStarted(true);
  };

  const handleKey = (e: React.KeyboardEvent) => {
    if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  };

  const formatTime = (iso: string) => {
    try {
      return new Date(iso).toLocaleTimeString("en-PK", { hour: "2-digit", minute: "2-digit" });
    } catch { return ""; }
  };

  return (
    <>
      {/* ── Floating Button ── */}
      <button
        onClick={() => setOpen(o => !o)}
        aria-label="Chat with us"
        style={{
          position: "fixed", bottom: "1.5rem", right: "1.5rem", zIndex: 1000,
          width: "58px", height: "58px", borderRadius: "50%",
          background: "linear-gradient(135deg,#1a1a1a,#000)",
          border: "none", cursor: "pointer", display: "flex",
          alignItems: "center", justifyContent: "center",
          boxShadow: "0 8px 24px rgba(0,0,0,0.3)",
          transition: "transform 0.2s",
        }}
        onMouseEnter={e => (e.currentTarget.style.transform = "scale(1.08)")}
        onMouseLeave={e => (e.currentTarget.style.transform = "scale(1)")}
      >
        {open
          ? <X style={{ color: "#fff", width: 22, height: 22 }} />
          : <MessageCircle style={{ color: "#fff", width: 24, height: 24 }} />
        }
        {!open && unread > 0 && (
          <span style={{
            position: "absolute", top: "2px", right: "2px",
            background: "#ef4444", color: "#fff", fontSize: "0.65rem",
            fontWeight: 800, padding: "2px 6px", borderRadius: "10px",
            minWidth: "18px", textAlign: "center",
          }}>
            {unread}
          </span>
        )}
      </button>

      {/* ── Chat Box ── */}
      {open && (
        <div style={{
          position: "fixed", bottom: "5.5rem", right: "1.5rem", zIndex: 999,
          width: "min(360px, calc(100vw - 2rem))",
          background: "#fff",
          borderRadius: "16px",
          boxShadow: "0 20px 60px rgba(0,0,0,0.25)",
          display: "flex", flexDirection: "column",
          overflow: "hidden",
          animation: "chatSlideIn 0.22s ease",
        }}>
          <style>{`
            @keyframes chatSlideIn {
              from { opacity:0; transform:translateY(16px) scale(0.97); }
              to   { opacity:1; transform:translateY(0) scale(1); }
            }
          `}</style>

          {/* Header */}
          <div style={{
            background: "linear-gradient(135deg,#1a1a1a,#000)",
            padding: "1rem 1.1rem",
            display: "flex", alignItems: "center", gap: "0.75rem",
          }}>
            <div style={{
              width: 36, height: 36, borderRadius: "50%",
              background: "rgba(245,158,11,0.2)",
              border: "2px solid rgba(245,158,11,0.4)",
              display: "flex", alignItems: "center", justifyContent: "center",
              flexShrink: 0,
            }}>
              <MessageCircle style={{ width: 16, height: 16, color: "#f59e0b" }} />
            </div>
            <div style={{ flex: 1 }}>
              <p style={{ margin: 0, color: "#fff", fontWeight: 800, fontSize: "0.85rem" }}>
                Snack Zone Support
              </p>
              <p style={{ margin: 0, color: "rgba(255,255,255,0.5)", fontSize: "0.7rem", display: "flex", alignItems: "center", gap: "0.3rem" }}>
                <span style={{ width: 6, height: 6, borderRadius: "50%", background: "#22c55e", display: "inline-block" }} />
                Live — Order #{orderId}
              </p>
            </div>
            <button onClick={() => setOpen(false)} style={{ background: "none", border: "none", cursor: "pointer", color: "rgba(255,255,255,0.5)", padding: 4 }}>
              <ChevronDown style={{ width: 18, height: 18 }} />
            </button>
          </div>

          {/* Messages */}
          <div style={{
            flex: 1, overflowY: "auto", padding: "1rem",
            display: "flex", flexDirection: "column", gap: "0.6rem",
            minHeight: "240px", maxHeight: "320px",
            background: "#f8f9fa",
          }}>
            {/* Welcome message */}
            <div style={{
              background: "#fff", borderRadius: "10px",
              padding: "0.7rem 0.9rem", fontSize: "0.8rem",
              color: "#6b7280", border: "1px solid rgba(0,0,0,0.07)",
              maxWidth: "80%", lineHeight: 1.5,
            }}>
              👋 Hi <strong>{customerName || "there"}</strong>! Order #{orderId} receive ho gaya.
              Koi sawaal ho toh yahan poochh saktay hain — jaise <em>&quot;mera order kab aayega?&quot;</em>
            </div>

            {messages.map(msg => (
              <div key={msg.id} style={{
                display: "flex",
                justifyContent: msg.sender === "user" ? "flex-end" : "flex-start",
              }}>
                <div style={{
                  maxWidth: "80%", padding: "0.55rem 0.85rem",
                  borderRadius: msg.sender === "user"
                    ? "14px 14px 4px 14px"
                    : "14px 14px 14px 4px",
                  background: msg.sender === "user"
                    ? "linear-gradient(135deg,#1a1a1a,#000)"
                    : "#fff",
                  color: msg.sender === "user" ? "#fff" : "#1a1a1a",
                  fontSize: "0.82rem", lineHeight: 1.5,
                  border: msg.sender === "receptionist" ? "1px solid rgba(0,0,0,0.08)" : "none",
                  boxShadow: "0 2px 6px rgba(0,0,0,0.06)",
                }}>
                  {msg.sender === "receptionist" && (
                    <p style={{ margin: "0 0 2px", fontSize: "0.65rem", fontWeight: 700, color: "#f59e0b", textTransform: "uppercase", letterSpacing: "0.05em" }}>
                      Receptionist
                    </p>
                  )}
                  <p style={{ margin: 0 }}>{msg.message}</p>
                  <p style={{ margin: "3px 0 0", fontSize: "0.65rem", opacity: 0.5, textAlign: "right" }}>
                    {formatTime(msg.created_at)}
                  </p>
                </div>
              </div>
            ))}
            <div ref={bottomRef} />
          </div>

          {/* Input */}
          <div style={{
            padding: "0.75rem",
            borderTop: "1px solid rgba(0,0,0,0.07)",
            background: "#fff",
            display: "flex", gap: "0.5rem", alignItems: "center",
          }}>
            <input
              ref={inputRef}
              value={input}
              onChange={e => setInput(e.target.value)}
              onKeyDown={handleKey}
              placeholder="Message likhein..."
              style={{
                flex: 1, padding: "0.6rem 0.9rem",
                border: "2px solid rgba(0,0,0,0.08)",
                borderRadius: "10px", fontSize: "0.85rem",
                fontFamily: "inherit", outline: "none",
                transition: "border-color 0.2s",
              }}
              onFocus={e => (e.target.style.borderColor = "#1a1a1a")}
              onBlur={e => (e.target.style.borderColor = "rgba(0,0,0,0.08)")}
            />
            <button
              onClick={sendMessage}
              disabled={!input.trim() || sending}
              style={{
                width: 40, height: 40, borderRadius: "10px",
                background: input.trim() && !sending
                  ? "linear-gradient(135deg,#1a1a1a,#000)"
                  : "#e5e7eb",
                border: "none", cursor: input.trim() && !sending ? "pointer" : "not-allowed",
                display: "flex", alignItems: "center", justifyContent: "center",
                transition: "all 0.2s", flexShrink: 0,
              }}
            >
              {sending
                ? <Loader2 style={{ width: 16, height: 16, color: "#9ca3af", animation: "spin 1s linear infinite" }} />
                : <Send style={{ width: 16, height: 16, color: input.trim() ? "#fff" : "#9ca3af" }} />
              }
            </button>
          </div>
        </div>
      )}
    </>
  );
}
