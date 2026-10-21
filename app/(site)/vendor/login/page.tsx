"use client";

import { useState } from "react";
import { API_BASE } from "@/lib/api";
import { Store, Mail, Lock, ArrowLeft, AlertCircle, Loader2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";

export default function VendorLogin() {
  const router = useRouter();
  const [form, setForm] = useState({
    email: "",
    password: "",
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const res = await fetch(`${API_BASE}/vendor_login.php`, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          email: form.email,
          password: form.password,
        }),
        credentials: "include",
      });

      const data = await res.json();

      if (data.success) {
        // Store vendor info in localStorage
        localStorage.setItem("vendor", JSON.stringify(data.vendor));
        // Redirect to vendor dashboard
        router.push("/vendor/dashboard");
      } else {
        setError(data.message || "Login failed");
      }
    } catch (err) {
      setError("Network error. Please try again.");
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: "calc(100vh - 80px)",
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      padding: "2rem",
      background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
    }}>
      <div style={{
        width: "100%",
        maxWidth: "450px",
        background: "#fff",
        borderRadius: "24px",
        boxShadow: "0 20px 60px rgba(0,0,0,0.3)",
        overflow: "hidden",
      }}>
        {/* Header */}
        <div style={{
          padding: "2.5rem 2rem 2rem",
          background: "linear-gradient(135deg, #4169e1 0%, #2563eb 100%)",
          color: "#fff",
          textAlign: "center",
        }}>
          <div style={{
            width: "80px",
            height: "80px",
            margin: "0 auto 1.5rem",
            background: "rgba(255,255,255,0.2)",
            borderRadius: "50%",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            backdropFilter: "blur(10px)",
          }}>
            <Store style={{ width: "40px", height: "40px" }} />
          </div>
          <h1 style={{ fontSize: "2rem", fontWeight: 800, marginBottom: "0.5rem", letterSpacing: "-0.02em" }}>
            Chef Login
          </h1>
          <p style={{ fontSize: "0.95rem", opacity: 0.95, fontWeight: 500 }}>
            Access your store dashboard
          </p>
        </div>

        {/* Form */}
        <div style={{ padding: "2rem" }}>
          {error && (
            <div style={{
              padding: "1rem 1.2rem",
              background: "#fee2e2",
              border: "1px solid #ef4444",
              borderRadius: "12px",
              marginBottom: "1.5rem",
              display: "flex",
              alignItems: "start",
              gap: "0.8rem",
              color: "#991b1b",
              fontSize: "0.9rem",
            }}>
              <AlertCircle style={{ width: "20px", height: "20px", flexShrink: 0, marginTop: "2px" }} />
              <div>{error}</div>
            </div>
          )}

          <form onSubmit={handleSubmit}>
            <div style={{ marginBottom: "1.5rem" }}>
              <label style={{
                display: "block",
                marginBottom: "0.5rem",
                fontSize: "0.85rem",
                fontWeight: 700,
                color: "#1a1a1a",
                textTransform: "uppercase",
                letterSpacing: "0.05em",
              }}>
                <Mail style={{ width: "14px", height: "14px", display: "inline", marginRight: "6px", verticalAlign: "middle" }} />
                Email Address
              </label>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                required
                style={{
                  width: "100%",
                  padding: "0.9rem 1.1rem",
                  border: "2px solid rgba(0,0,0,0.1)",
                  borderRadius: "12px",
                  fontSize: "0.95rem",
                  outline: "none",
                  transition: "all 0.2s",
                  fontFamily: "inherit",
                }}
                onFocus={(e) => {
                  e.currentTarget.style.borderColor = "#4169e1";
                  e.currentTarget.style.boxShadow = "0 0 0 4px rgba(65,105,225,0.1)";
                }}
                onBlur={(e) => {
                  e.currentTarget.style.borderColor = "rgba(0,0,0,0.1)";
                  e.currentTarget.style.boxShadow = "none";
                }}
              />
            </div>

            <div style={{ marginBottom: "1.5rem" }}>
              <label style={{
                display: "block",
                marginBottom: "0.5rem",
                fontSize: "0.85rem",
                fontWeight: 700,
                color: "#1a1a1a",
                textTransform: "uppercase",
                letterSpacing: "0.05em",
              }}>
                <Lock style={{ width: "14px", height: "14px", display: "inline", marginRight: "6px", verticalAlign: "middle" }} />
                Password
              </label>
              <input
                type="password"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                required
                style={{
                  width: "100%",
                  padding: "0.9rem 1.1rem",
                  border: "2px solid rgba(0,0,0,0.1)",
                  borderRadius: "12px",
                  fontSize: "0.95rem",
                  outline: "none",
                  transition: "all 0.2s",
                  fontFamily: "inherit",
                }}
                onFocus={(e) => {
                  e.currentTarget.style.borderColor = "#4169e1";
                  e.currentTarget.style.boxShadow = "0 0 0 4px rgba(65,105,225,0.1)";
                }}
                onBlur={(e) => {
                  e.currentTarget.style.borderColor = "rgba(0,0,0,0.1)";
                  e.currentTarget.style.boxShadow = "none";
                }}
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              style={{
                width: "100%",
                padding: "1rem",
                background: loading ? "#9ca3af" : "linear-gradient(135deg, #4169e1 0%, #2563eb 100%)",
                color: "#fff",
                border: "none",
                borderRadius: "12px",
                fontSize: "1rem",
                fontWeight: 700,
                cursor: loading ? "not-allowed" : "pointer",
                transition: "all 0.3s",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                gap: "0.5rem",
                textTransform: "uppercase",
                letterSpacing: "0.05em",
              }}
              onMouseEnter={(e) => {
                if (!loading) {
                  e.currentTarget.style.transform = "translateY(-2px)";
                  e.currentTarget.style.boxShadow = "0 8px 24px rgba(65,105,225,0.4)";
                }
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.transform = "translateY(0)";
                e.currentTarget.style.boxShadow = "none";
              }}
            >
              {loading ? (
                <>
                  <Loader2 style={{ width: "20px", height: "20px", animation: "spin 1s linear infinite" }} />
                  Logging in...
                </>
              ) : (
                "Login"
              )}
            </button>
          </form>

          <div style={{
            marginTop: "2rem",
            paddingTop: "1.5rem",
            borderTop: "1px solid rgba(0,0,0,0.1)",
            textAlign: "center",
            fontSize: "0.85rem",
            color: "#666",
          }}>
            Don't have an account?{" "}
            <Link
              href="/vendor/register"
              style={{
                color: "#4169e1",
                textDecoration: "none",
                fontWeight: 700,
              }}
            >
              Register as Chef
            </Link>
          </div>

          <div style={{ textAlign: "center", marginTop: "1rem" }}>
            <Link
              href="/"
              style={{
                color: "#4169e1",
                textDecoration: "none",
                fontSize: "0.85rem",
                fontWeight: 600,
                display: "inline-flex",
                alignItems: "center",
                gap: "0.4rem",
              }}
            >
              <ArrowLeft style={{ width: "16px", height: "16px" }} />
              Back to Home
            </Link>
          </div>
        </div>
      </div>

      <style jsx>{`
        @keyframes spin {
          from {
            transform: rotate(0deg);
          }
          to {
            transform: rotate(360deg);
          }
        }
      `}</style>
    </div>
  );
}
