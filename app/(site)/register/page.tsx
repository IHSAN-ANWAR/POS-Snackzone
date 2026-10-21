"use client";

import { useState, useRef, useEffect } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { User, Phone, Mail, ArrowRight, CheckCircle, Loader2, RotateCcw } from "lucide-react";
import { API_BASE } from "@/lib/api";

type Step = "form" | "otp" | "done";

interface RegForm { name: string; phone: string; email: string; }
type RegErrors = Partial<Record<keyof RegForm, string>>;

function validate(f: RegForm): RegErrors {
  const e: RegErrors = {};
  if (!f.name.trim()) e.name = "Name is required.";
  else if (f.name.trim().length < 2) e.name = "At least 2 characters.";
  if (!f.phone.trim()) e.phone = "Phone number is required.";
  else if (!/^[0-9+\s\-]{7,15}$/.test(f.phone.trim())) e.phone = "Enter a valid phone number.";
  if (!f.email.trim()) e.email = "Email is required.";
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) e.email = "Enter a valid email.";
  return e;
}

function FieldError({ msg }: { msg?: string }) {
  if (!msg) return null;
  return <p className="text-[#e63946] text-xs mt-1.5 font-medium">{msg}</p>;
}

// ── 6-box OTP input ───────────────────────────────────────
function OtpInput({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const inputs = useRef<(HTMLInputElement | null)[]>([]);

  const handleKey = (i: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Backspace" && !value[i] && i > 0) {
      inputs.current[i - 1]?.focus();
    }
  };

  const handleChange = (i: number, e: React.ChangeEvent<HTMLInputElement>) => {
    const char = e.target.value.replace(/\D/g, "").slice(-1);
    const arr  = value.split("");
    arr[i]     = char;
    const next = arr.join("").padEnd(6, "").slice(0, 6);
    onChange(next.trimEnd());
    if (char && i < 5) inputs.current[i + 1]?.focus();
  };

  const handlePaste = (e: React.ClipboardEvent) => {
    const text = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
    if (text.length === 6) {
      onChange(text);
      inputs.current[5]?.focus();
    }
    e.preventDefault();
  };

  return (
    <div style={{ display: "flex", gap: "10px", justifyContent: "center", margin: "1.5rem 0" }}>
      {[0, 1, 2, 3, 4, 5].map(i => (
        <input
          key={i}
          ref={el => { inputs.current[i] = el; }}
          type="text"
          inputMode="numeric"
          maxLength={1}
          value={value[i] ?? ""}
          onChange={e => handleChange(i, e)}
          onKeyDown={e => handleKey(i, e)}
          onPaste={handlePaste}
          style={{
            width: "46px", height: "56px", textAlign: "center",
            fontSize: "1.5rem", fontWeight: 900, fontFamily: "Inter, sans-serif",
            border: value[i] ? "2px solid #000" : "1.5px solid rgba(0,0,0,0.2)",
            borderRadius: "4px", outline: "none", background: "#fff",
            transition: "border-color 0.15s",
          }}
        />
      ))}
    </div>
  );
}

// ── Main page ─────────────────────────────────────────────
export default function RegisterPage() {
  const router  = useRouter();
  const blank: RegForm = { name: "", phone: "", email: "" };

  const [step, setStep]         = useState<Step>("form");
  const [form, setForm]         = useState<RegForm>(blank);
  const [errors, setErrors]     = useState<RegErrors>({});
  const [touched, setTouched]   = useState<Partial<Record<keyof RegForm, boolean>>>({});
  const [loading, setLoading]   = useState(false);
  const [serverErr, setServerErr] = useState("");

  // OTP step state
  const [otp, setOtp]           = useState("");
  const [otpErr, setOtpErr]     = useState("");
  const [verifying, setVerifying] = useState(false);
  const [resendTimer, setResendTimer] = useState(0);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const [devOtp, setDevOtp]     = useState(""); // shown on screen when email is disabled

  // Countdown timer for resend
  const startTimer = () => {
    setResendTimer(60);
    if (timerRef.current) clearInterval(timerRef.current);
    timerRef.current = setInterval(() => {
      setResendTimer(t => {
        if (t <= 1) { clearInterval(timerRef.current!); return 0; }
        return t - 1;
      });
    }, 1000);
  };

  useEffect(() => () => { if (timerRef.current) clearInterval(timerRef.current); }, []);

  const ic = (f: keyof RegForm) =>
    `contact-input${touched[f] && errors[f] ? " contact-input-error" : ""}`;

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const updated = { ...form, [e.target.name]: e.target.value };
    setForm(updated);
    if (touched[e.target.name as keyof RegForm]) setErrors(validate(updated));
  };

  const handleBlur = (e: React.FocusEvent<HTMLInputElement>) => {
    const n = e.target.name as keyof RegForm;
    setTouched(t => ({ ...t, [n]: true }));
    setErrors(validate(form));
  };

  // Step 1 — submit form → send OTP
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerErr("");
    const allTouched = Object.fromEntries(Object.keys(form).map(k => [k, true])) as Record<keyof RegForm, boolean>;
    setTouched(allTouched);
    const errs = validate(form);
    setErrors(errs);
    if (Object.keys(errs).length > 0) return;

    setLoading(true);
    try {
      const body = new URLSearchParams({ ...form });
      const res  = await fetch(`${API_BASE}/register.php`, { method: "POST", body });
      const data = await res.json();

      if (data.success) {
        setOtp("");
        setOtpErr("");
        if (data.otp) setDevOtp(data.otp); // auto-fill when email disabled
        setStep("otp");
        startTimer();
      } else {
        if (data.field) setErrors({ [data.field]: data.message } as RegErrors);
        else setServerErr(data.message ?? "Registration failed. Please try again.");
      }
    } catch {
      setServerErr("Could not reach server. Please check your connection and try again.");
    } finally {
      setLoading(false);
    }
  };

  // Step 2 — verify OTP
  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    if (otp.length < 6) { setOtpErr("Please enter the complete 6-digit code."); return; }
    setOtpErr("");
    setVerifying(true);
    try {
      const body = new URLSearchParams({ email: form.email, otp });
      const res  = await fetch(`${API_BASE}/verify_email.php`, { method: "POST", body });
      const data = await res.json();

      if (data.success) {
        setStep("done");
      } else {
        setOtpErr(data.message ?? "Invalid OTP. Please try again.");
        setOtp("");
      }
    } catch {
      setOtpErr("Network error. Please try again.");
    } finally {
      setVerifying(false);
    }
  };

  // Resend OTP
  const handleResend = async () => {
    if (resendTimer > 0) return;
    setOtpErr("");
    setOtp("");
    setLoading(true);
    try {
      const body = new URLSearchParams({ ...form });
      const res  = await fetch(`${API_BASE}/register.php`, { method: "POST", body });
      const data = await res.json();
      if (data.success) {
        startTimer();
        if (data.otp) setDevOtp(data.otp);
      }
      else setOtpErr(data.message ?? "Could not resend OTP.");
    } catch {
      setOtpErr("Network error.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-white min-h-[80vh]">

      {/* Header */}
      <div className="bg-black py-6 px-5">
        <div className="custom-container">
          <p className="text-xs font-bold tracking-widest text-white/50 uppercase mb-1">Snack Zone</p>
          <h1 className="font-black text-white leading-none"
            style={{ fontSize: "clamp(2rem,6vw,4rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
            Create Account
          </h1>
        </div>
      </div>

      <section className="py-14 px-5">
        <div className="custom-container" style={{ maxWidth: "460px" }}>
          <div style={{ border: "1px solid rgba(0,0,0,0.1)", borderRadius: "4px", padding: "32px" }}>

            {/* ── STEP 1: Form ── */}
            {step === "form" && (
              <>
                <h2 className="font-black text-black mb-1"
                  style={{ fontSize: "1.2rem", textTransform: "uppercase", letterSpacing: "-0.01em" }}>
                  Your Details
                </h2>
                <p className="text-[#888] text-sm mb-6">
                  Fill in your info — we will send a 6-digit code to your email.
                </p>

                <form onSubmit={handleSubmit} noValidate className="space-y-4">
                  <div>
                    <label className="contact-label"><User className="w-3 h-3" /> Full Name</label>
                    <input name="name" value={form.name}
                      onChange={handleChange} onBlur={handleBlur}
                      placeholder="Full Name" className={ic("name")} />
                    <FieldError msg={touched.name ? errors.name : undefined} />
                  </div>

                  <div>
                    <label className="contact-label"><Phone className="w-3 h-3" /> Phone Number</label>
                    <input name="phone" value={form.phone}
                      onChange={handleChange} onBlur={handleBlur}
                      placeholder="0310 0000000" className={ic("phone")} />
                    <FieldError msg={touched.phone ? errors.phone : undefined} />
                  </div>

                  <div>
                    <label className="contact-label"><Mail className="w-3 h-3" /> Email Address</label>
                    <input name="email" type="email" value={form.email}
                      onChange={handleChange} onBlur={handleBlur}
                      placeholder="you@example.com" className={ic("email")} />
                    <p className="text-[#aaa] text-xs mt-1">
                      A 6-digit verification code will be sent here.
                    </p>
                    <FieldError msg={touched.email ? errors.email : undefined} />
                  </div>

                  {serverErr && (
                    <p className="text-[#e63946] text-xs font-medium bg-red-50 border border-red-100 px-3 py-2">
                      {serverErr}
                    </p>
                  )}

                  <button type="submit" disabled={loading}
                    className="btn-black w-full justify-center py-3 disabled:opacity-50 disabled:cursor-not-allowed">
                    {loading
                      ? <><Loader2 className="w-4 h-4 animate-spin" /> Sending code…</>
                      : <><ArrowRight className="w-4 h-4" /> Send Verification Code</>}
                  </button>
                </form>

                <p className="text-center text-xs text-[#888] mt-5">
                  Already have an account?{" "}
                  <Link href="/order" className="text-black font-bold hover:underline">Order Now</Link>
                </p>
              </>
            )}

            {/* ── STEP 2: OTP Entry ── */}
            {step === "otp" && (
              <>
                {/* Email icon */}
                <div style={{ display:"flex", flexDirection:"column", alignItems:"center", marginBottom:"1.2rem" }}>
                  <div style={{
                    width: "56px", height: "56px", background: "#000",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    marginBottom: "0.8rem",
                  }}>
                    <Mail className="w-6 h-6 text-white" />
                  </div>
                  <h2 className="font-black text-black"
                    style={{ fontSize: "1.2rem", textTransform: "uppercase", letterSpacing: "-0.01em" }}>
                    Enter OTP
                  </h2>
                  <p className="text-[#888] text-sm mt-1 text-center">
                    We sent a 6-digit code to
                  </p>
                  <p className="font-black text-black text-sm">{form.email}</p>
                </div>

                <form onSubmit={handleVerify}>
                  <OtpInput value={otp} onChange={setOtp} />

                  {/* Show OTP on screen when email is disabled */}
                  {devOtp && (
                    <div style={{
                      background: "#fffbeb", border: "1px solid #fbbf24",
                      borderRadius: "6px", padding: "10px 14px",
                      marginBottom: "12px", textAlign: "center",
                    }}>
                      <p style={{ fontSize: "0.72rem", color: "#92400e", fontWeight: 700, marginBottom: "4px" }}>
                        📧 Email disabled — your OTP code is:
                      </p>
                      <p style={{ fontSize: "1.6rem", fontWeight: 900, letterSpacing: "0.3em", color: "#000" }}>
                        {devOtp}
                      </p>
                    </div>
                  )}

                  {otpErr && (
                    <p className="text-[#e63946] text-xs font-medium text-center mb-3">{otpErr}</p>
                  )}

                  <button type="submit" disabled={verifying || otp.length < 6}
                    className="btn-black w-full justify-center py-3 disabled:opacity-50 disabled:cursor-not-allowed">
                    {verifying
                      ? <><Loader2 className="w-4 h-4 animate-spin" /> Verifying…</>
                      : <><CheckCircle className="w-4 h-4" /> Verify Code</>}
                  </button>
                </form>

                {/* Resend + change email */}
                <div style={{ display:"flex", flexDirection:"column", alignItems:"center", gap:"0.6rem", marginTop:"1.2rem" }}>
                  <button
                    onClick={handleResend}
                    disabled={resendTimer > 0 || loading}
                    style={{
                      background: "none", border: "none", cursor: resendTimer > 0 ? "default" : "pointer",
                      fontSize: "0.8rem", fontWeight: 700, fontFamily: "Inter, sans-serif",
                      color: resendTimer > 0 ? "#bbb" : "#000", display: "flex", alignItems: "center", gap: "5px",
                    }}
                  >
                    {loading
                      ? <><Loader2 className="w-3.5 h-3.5 animate-spin" /> Sending…</>
                      : resendTimer > 0
                        ? `Resend in ${resendTimer}s`
                        : <><RotateCcw className="w-3.5 h-3.5" /> Resend Code</>}
                  </button>
                  <button
                    onClick={() => { setStep("form"); setServerErr(""); setOtp(""); setOtpErr(""); }}
                    style={{ background:"none", border:"none", cursor:"pointer", fontSize:"0.75rem", color:"#aaa", fontFamily:"Inter,sans-serif" }}
                  >
                    ← Change email address
                  </button>
                </div>
              </>
            )}

            {/* ── STEP 3: Done ── */}
            {step === "done" && (
              <div className="text-center py-6">
                <div style={{
                  width:"64px", height:"64px", background:"#000",
                  display:"flex", alignItems:"center", justifyContent:"center",
                  margin:"0 auto 1.2rem",
                }}>
                  <CheckCircle className="w-8 h-8 text-white" />
                </div>
                <h2 className="font-black text-black mb-2"
                  style={{ fontSize: "1.3rem", textTransform: "uppercase" }}>
                  Account Verified!
                </h2>
                <p className="text-[#888] text-sm mb-6">
                  Welcome, <strong>{form.name}</strong>! You can now place orders.
                </p>
                <button
                  onClick={() => router.push("/menu")}
                  className="btn-black w-full justify-center py-3"
                >
                  <ArrowRight className="w-4 h-4" /> Go to Menu
                </button>
              </div>
            )}

          </div>
        </div>
      </section>
    </div>
  );
}
