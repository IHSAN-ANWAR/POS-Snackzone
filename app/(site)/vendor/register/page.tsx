"use client";

import { useState } from "react";
import { API_BASE } from "@/lib/api";
import { Upload, Store, User, CreditCard, Phone, Mail, Lock, ArrowLeft } from "lucide-react";
import Image from "next/image";
import Link from "next/link";

export default function VendorRegister() {
  const [form, setForm] = useState({
    name: "",
    email: "",
    phone: "",
    cnic: "",
    password: "",
    confirmPassword: "",
    store_name: "",
  });

  const [profileImage, setProfileImage] = useState<File | null>(null);
  const [preview, setPreview] = useState<string>("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState(false);

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setProfileImage(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");

    // Validation
    if (form.password !== form.confirmPassword) {
      setError("Passwords do not match");
      return;
    }

    if (form.cnic.length !== 13) {
      setError("CNIC must be 13 digits");
      return;
    }

    if (!profileImage) {
      setError("Please upload your profile image");
      return;
    }

    setLoading(true);

    const formData = new FormData();
    formData.append("name", form.name);
    formData.append("email", form.email);
    formData.append("phone", form.phone);
    formData.append("cnic", form.cnic);
    formData.append("password", form.password);
    formData.append("store_name", form.store_name);
    formData.append("profile_image", profileImage);

    try {
      const res = await fetch(`${API_BASE}/vendor_register.php`, {
        method: "POST",
        body: formData,
      });

      const data = await res.json();

      if (data.success) {
        setSuccess(true);
      } else {
        setError(data.message || "Registration failed");
      }
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div style={{ minHeight: "100vh", background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)", display: "flex", alignItems: "center", justifyContent: "center", padding: "2rem" }}>
        <div style={{ maxWidth: "500px", background: "#fff", borderRadius: "20px", padding: "3rem", textAlign: "center", boxShadow: "0 20px 60px rgba(0,0,0,0.3)" }}>
          <div style={{ width: "80px", height: "80px", background: "#10b981", borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 1.5rem", color: "#fff", fontSize: "2rem" }}>
            ✓
          </div>
          <h2 style={{ fontSize: "1.8rem", fontWeight: 900, color: "#000", marginBottom: "1rem" }}>Registration Successful!</h2>
          <p style={{ color: "#666", marginBottom: "2rem", lineHeight: 1.6 }}>
            Your store registration is pending approval. We&apos;ll notify you via email once approved.
          </p>
          <Link href="/vendor/login" style={{ display: "inline-block", padding: "1rem 2rem", background: "#4169e1", color: "#fff", textDecoration: "none", borderRadius: "12px", fontWeight: 700 }}>
            Go to Login
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div style={{ minHeight: "100vh", background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)", padding: "2rem 1rem" }}>
      <div style={{ maxWidth: "600px", margin: "0 auto", background: "#fff", borderRadius: "20px", padding: "2.5rem", boxShadow: "0 20px 60px rgba(0,0,0,0.3)" }}>

        {/* Header */}
        <div style={{ textAlign: "center", marginBottom: "2rem" }}>
          <div style={{ width: "70px", height: "70px", background: "linear-gradient(135deg, #4169e1, #3557c7)", borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 1rem", color: "#fff", fontSize: "1.8rem" }}>
            <Store />
          </div>
          <h1 style={{ fontSize: "1.8rem", fontWeight: 900, background: "linear-gradient(135deg, #4169e1, #764ba2)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent", marginBottom: "0.5rem" }}>
            Become a Chef Partner
          </h1>
          <p style={{ color: "#6b7280", fontSize: "0.9rem" }}>Register your store and start selling</p>
        </div>

        {/* Error */}
        {error && (
          <div style={{ background: "linear-gradient(135deg, #ef4444, #dc2626)", color: "#fff", padding: "1rem", borderRadius: "10px", marginBottom: "1.5rem", fontSize: "0.9rem" }}>
            {error}
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit}>

          {/* Profile Image Upload */}
          <div style={{ marginBottom: "1.5rem", textAlign: "center" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.8rem" }}>
              Profile Image *
            </label>
            <div style={{ position: "relative", width: "120px", height: "120px", margin: "0 auto", cursor: "pointer" }}>
              <input
                type="file"
                accept="image/*"
                onChange={handleImageChange}
                style={{ display: "none" }}
                id="profileImage"
                required
              />
              <label htmlFor="profileImage" style={{ cursor: "pointer", width: "100%", height: "100%", borderRadius: "50%", border: "3px dashed #4169e1", display: "flex", alignItems: "center", justifyContent: "center", background: "#f9fafb", overflow: "hidden" }}>
                {preview ? (
                  <Image src={preview} alt="Preview" width={120} height={120} style={{ objectFit: "cover", width: "100%", height: "100%" }} />
                ) : (
                  <Upload style={{ width: "32px", height: "32px", color: "#4169e1" }} />
                )}
              </label>
            </div>
          </div>

          {/* Store Name */}
          <div style={{ marginBottom: "1.2rem" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.5rem" }}>
              Store Name *
            </label>
            <div style={{ position: "relative" }}>
              <Store style={{ position: "absolute", left: "1rem", top: "50%", transform: "translateY(-50%)", width: "18px", height: "18px", color: "#9ca3af" }} />
              <input
                type="text"
                value={form.store_name}
                onChange={(e) => setForm({ ...form, store_name: e.target.value })}
                placeholder="My Kitchen"
                required
                style={{ width: "100%", padding: "0.95rem 1rem 0.95rem 3rem", border: "2px solid #e5e7eb", borderRadius: "12px", fontSize: "0.9rem", background: "#f9fafb", outline: "none" }}
              />
            </div>
          </div>

          {/* Full Name */}
          <div style={{ marginBottom: "1.2rem" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.5rem" }}>
              Full Name *
            </label>
            <div style={{ position: "relative" }}>
              <User style={{ position: "absolute", left: "1rem", top: "50%", transform: "translateY(-50%)", width: "18px", height: "18px", color: "#9ca3af" }} />
              <input
                type="text"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder="Muhammad Ali"
                required
                style={{ width: "100%", padding: "0.95rem 1rem 0.95rem 3rem", border: "2px solid #e5e7eb", borderRadius: "12px", fontSize: "0.9rem", background: "#f9fafb", outline: "none" }}
              />
            </div>
          </div>

          {/* CNIC */}
          <div style={{ marginBottom: "1.2rem" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.5rem" }}>
              CNIC Number (13 digits) *
            </label>
            <div style={{ position: "relative" }}>
              <CreditCard style={{ position: "absolute", left: "1rem", top: "50%", transform: "translateY(-50%)", width: "18px", height: "18px", color: "#9ca3af" }} />
              <input
                type="text"
                value={form.cnic}
                onChange={(e) => setForm({ ...form, cnic: e.target.value.replace(/\D/g, "").slice(0, 13) })}
                placeholder="1234567890123"
                required
                maxLength={13}
                style={{ width: "100%", padding: "0.95rem 1rem 0.95rem 3rem", border: "2px solid #e5e7eb", borderRadius: "12px", fontSize: "0.9rem", background: "#f9fafb", outline: "none" }}
              />
            </div>
          </div>

          {/* Phone */}
          <div style={{ marginBottom: "1.2rem" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.5rem" }}>
              Phone Number *
            </label>
            <div style={{ position: "relative" }}>
              <Phone style={{ position: "absolute", left: "1rem", top: "50%", transform: "translateY(-50%)", width: "18px", height: "18px", color: "#9ca3af" }} />
              <input
                type="tel"
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                placeholder="03001234567"
                required
                style={{ width: "100%", padding: "0.95rem 1rem 0.95rem 3rem", border: "2px solid #e5e7eb", borderRadius: "12px", fontSize: "0.9rem", background: "#f9fafb", outline: "none" }}
              />
            </div>
          </div>

          {/* Email */}
          <div style={{ marginBottom: "1.2rem" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.5rem" }}>
              Email *
            </label>
            <div style={{ position: "relative" }}>
              <Mail style={{ position: "absolute", left: "1rem", top: "50%", transform: "translateY(-50%)", width: "18px", height: "18px", color: "#9ca3af" }} />
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                placeholder="chef@example.com"
                required
                style={{ width: "100%", padding: "0.95rem 1rem 0.95rem 3rem", border: "2px solid #e5e7eb", borderRadius: "12px", fontSize: "0.9rem", background: "#f9fafb", outline: "none" }}
              />
            </div>
          </div>

          {/* Password */}
          <div style={{ marginBottom: "1.2rem" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.5rem" }}>
              Password *
            </label>
            <div style={{ position: "relative" }}>
              <Lock style={{ position: "absolute", left: "1rem", top: "50%", transform: "translateY(-50%)", width: "18px", height: "18px", color: "#9ca3af" }} />
              <input
                type="password"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                placeholder="••••••••"
                required
                minLength={6}
                style={{ width: "100%", padding: "0.95rem 1rem 0.95rem 3rem", border: "2px solid #e5e7eb", borderRadius: "12px", fontSize: "0.9rem", background: "#f9fafb", outline: "none" }}
              />
            </div>
          </div>

          {/* Confirm Password */}
          <div style={{ marginBottom: "1.5rem" }}>
            <label style={{ fontSize: "0.75rem", fontWeight: 800, textTransform: "uppercase", color: "#374151", display: "block", marginBottom: "0.5rem" }}>
              Confirm Password *
            </label>
            <div style={{ position: "relative" }}>
              <Lock style={{ position: "absolute", left: "1rem", top: "50%", transform: "translateY(-50%)", width: "18px", height: "18px", color: "#9ca3af" }} />
              <input
                type="password"
                value={form.confirmPassword}
                onChange={(e) => setForm({ ...form, confirmPassword: e.target.value })}
                placeholder="••••••••"
                required
                minLength={6}
                style={{ width: "100%", padding: "0.95rem 1rem 0.95rem 3rem", border: "2px solid #e5e7eb", borderRadius: "12px", fontSize: "0.9rem", background: "#f9fafb", outline: "none" }}
              />
            </div>
          </div>

          {/* Submit */}
          <button
            type="submit"
            disabled={loading}
            style={{ width: "100%", padding: "1rem", background: "linear-gradient(135deg, #4169e1, #3557c7)", color: "#fff", border: "none", borderRadius: "12px", fontSize: "0.85rem", fontWeight: 700, textTransform: "uppercase", cursor: loading ? "not-allowed" : "pointer", opacity: loading ? 0.6 : 1 }}
          >
            {loading ? "Registering..." : "Register Store"}
          </button>

          {/* Back Link */}
          <div style={{ marginTop: "1.5rem", textAlign: "center", paddingTop: "1.5rem", borderTop: "1px solid #e5e7eb" }}>
            <Link href="/vendor/login" style={{ color: "#4169e1", textDecoration: "none", fontSize: "0.85rem", fontWeight: 600, display: "inline-flex", alignItems: "center", gap: "0.4rem" }}>
              <ArrowLeft style={{ width: "16px", height: "16px" }} />
              Already have an account? Login
            </Link>
          </div>
        </form>

      </div>
    </div>
  );
}
