"use client";

import { useState, useRef, useEffect } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Mail, ArrowRight, CheckCircle, Loader2, RotateCcw } from "lucide-react";
import { API_BASE } from "@/lib/api";
import { setUser } from "@/lib/auth";

type Step = "email" | "otp" | "done";

// ── 6-box OTP input ───────────────────────────────────────
function OtpInput({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const inputs = useRef<(HTMLInputElement | null)[]>([]);

  const handleKey = (i: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Backspace" && !value[i] && i > 0) inputs.current[i - 1]?.focus();
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
    if (text.length === 6) { onChange(text); inputs.current[5]?.focus(); }
    e.preventDefault();
  };

  return (
    <div style={{ display: "flex", gap: "10px", justifyContent: "center", margin: "1.5rem 0" }}>
      {[0,1,2,3,4,5].map(i => (
        <input key={i}
          ref={el => { inputs.current[i] = el; }}
          type="text" inputMode="numeric" maxLength={1}
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

export default function LoginPage() {
  const router  = useRouter();
  const [step, setStep]           = useState<Step>("email");
  const [email, setEmail]         = useState("");
  const [userName, setUserName]   = useState("");
  const [otp, setOtp]             = useState("");
  const [loading, setLoading]     = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [error, setError]         = useState("");
  const [otpErr, setOtpErr]       = useState("");
  const [resendTimer, setResendTimer] = useState(0);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const [devOtp, setDevOtp]       = useState(""); // shown on screen when email is disabled

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

  // Step 1 — Send OTP
  const handleSendOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim()) { setError("Please enter your email."); return; }
    setError(""); setLoading(true);
    try {
      const body = new URLSearchParams({ email });
      const res  = await fetch(`${API_BASE}/login_user.php`, { method: "POST", body });
      const data = await res.json();
      if (data.success) {
        setUserName(data.name);
        setOtp(""); setOtpErr("");
        if (data.otp) setDevOtp(data.otp); // auto-fill when email disabled
        setStep("otp");
        startTimer();
      } else {
        setError(data.message ?? "Error. Please try again.");
      }
    } catch {
      setError("Could not connect. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  // Step 2 — Verify OTP
  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    if (otp.length < 6) { setOtpErr("Please enter the 6-digit code."); return; }
    setOtpErr(""); setVerifying(true);
    try {
      const body = new URLSearchParams({ email, otp });
      const res  = await fetch(`${API_BASE}/verify_email.php`, { method: "POST", body });
      const data = await res.json();
      if (data.success) {
        setUser({ id: data.user.id, name: data.user.name, email: data.user.email, phone: data.user.phone });
        setStep("done");
        setTimeout(() => router.push("/profile"), 1500);
      } else {
        setOtpErr(data.message ?? "Invalid OTP.");
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
    setOtpErr(""); setOtp(""); setLoading(true);
    try {
      const body = new URLSearchParams({ email });
      const res  = await fetch(`${API_BASE}/login_user.php`, { method: "POST", body });
      const data = await res.json();
      if (data.success) {
        startTimer();
        if (data.otp) setDevOtp(data.otp);
      }
      else setOtpErr(data.message ?? "Could not resend.");
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
            Login
          </h1>
        </div>
      </div>

      <section className="py-14 px-5">
        <div className="custom-container" style={{ maxWidth: "420px" }}>
          <div style={{ border: "1px solid rgba(0,0,0,0.1)", padding: "32px" }}>

            {/* Step 1 — Email */}
            {step === "email" && (
              <>
                <h2 className="font-black text-black mb-1"
                  style={{ fontSize: "1.1rem", textTransform: "uppercase", letterSpacing: "-0.01em" }}>
                  Enter Your Email
                </h2>
                <p className="text-[#888] text-sm mb-6">
                  We&apos;ll send a 6-digit code to verify it&apos;s you.
                </p>
                <form onSubmit={handleSendOtp} noValidate>
                  <div style={{ position: "relative", marginBottom: "12px" }}>
                    <Mail className="w-4 h-4" style={{
                      position: "absolute", left: "12px", top: "50%",
                      transform: "translateY(-50%)", color: "#aaa",
                    }} />
                    <input
                      type="email" value={email}
                      onChange={e => setEmail(e.target.value)}
                      placeholder="you@example.com"
                      className="contact-input"
                      style={{ paddingLeft: "36px", width: "100%" }}
                      required
                    />
                  </div>
                  {error && <p style={{ color: "#dc2626", fontSize: "0.8rem", marginBottom: "10px", fontWeight: 600 }}>{error}</p>}
                  <button type="submit" disabled={loading} className="btn-black w-full justify-center py-3 disabled:opacity-50">
                    {loading
                      ? <><Loader2 className="w-4 h-4 animate-spin" /> Sending code…</>
                      : <><ArrowRight className="w-4 h-4" /> Send OTP</>}
                  </button>
                </form>
                <p className="text-center text-xs text-[#888] mt-5">
                  No account?{" "}
                  <Link href="/register" className="text-black font-bold hover:underline">Register here</Link>
                </p>
              </>
            )}

            {/* Step 2 — OTP */}
            {step === "otp" && (
              <>
                <div style={{ textAlign: "center", marginBottom: "1.2rem" }}>
                  <div style={{ width: "52px", height: "52px", background: "#000", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 0.8rem" }}>
                    <Mail className="w-6 h-6 text-white" />
                  </div>
                  <h2 className="font-black text-black"
                    style={{ fontSize: "1.1rem", textTransform: "uppercase", letterSpacing: "-0.01em" }}>
                    Enter OTP
                  </h2>
                  <p className="text-[#888] text-sm mt-1">Code sent to</p>
                  <p className="font-black text-black text-sm">{email}</p>
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
                  {otpErr && <p style={{ color: "#dc2626", fontSize: "0.78rem", textAlign: "center", marginBottom: "10px", fontWeight: 600 }}>{otpErr}</p>}
                  <button type="submit" disabled={verifying || otp.length < 6}
                    className="btn-black w-full justify-center py-3 disabled:opacity-50">
                    {verifying
                      ? <><Loader2 className="w-4 h-4 animate-spin" /> Verifying…</>
                      : <><CheckCircle className="w-4 h-4" /> Login</>}
                  </button>
                </form>
                <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: "0.6rem", marginTop: "1rem" }}>
                  <button onClick={handleResend} disabled={resendTimer > 0 || loading}
                    style={{ background: "none", border: "none", cursor: resendTimer > 0 ? "default" : "pointer",
                      fontSize: "0.8rem", fontWeight: 700, fontFamily: "Inter, sans-serif",
                      color: resendTimer > 0 ? "#bbb" : "#000", display: "flex", alignItems: "center", gap: "5px" }}>
                    {loading ? <><Loader2 className="w-3.5 h-3.5 animate-spin" /> Sending…</>
                      : resendTimer > 0 ? `Resend in ${resendTimer}s`
                      : <><RotateCcw className="w-3.5 h-3.5" /> Resend Code</>}
                  </button>
                  <button onClick={() => { setStep("email"); setError(""); setOtp(""); setOtpErr(""); }}
                    style={{ background: "none", border: "none", cursor: "pointer", fontSize: "0.75rem", color: "#aaa", fontFamily: "Inter,sans-serif" }}>
                    ← Change email
                  </button>
                </div>
              </>
            )}

            {/* Step 3 — Done */}
            {step === "done" && (
              <div style={{ textAlign: "center", padding: "1.5rem 0" }}>
                <div style={{ width: "56px", height: "56px", background: "#000", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 1rem" }}>
                  <CheckCircle className="w-7 h-7 text-white" />
                </div>
                <h2 className="font-black text-black mb-2"
                  style={{ fontSize: "1.2rem", textTransform: "uppercase" }}>
                  Welcome, {userName}!
                </h2>
                <p className="text-[#888] text-sm">Redirecting to your profile…</p>
              </div>
            )}

          </div>
        </div>
      </section>
    </div>
  );
}
