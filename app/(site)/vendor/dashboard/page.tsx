"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Store, Package, ShoppingBag, TrendingUp, LogOut, User, Settings } from "lucide-react";
import Link from "next/link";

interface Vendor {
  id: number;
  name: string;
  email: string;
  phone: string;
  status: "pending" | "approved" | "rejected" | "suspended";
  store_name: string;
  is_active: boolean;
}

export default function VendorDashboard() {
  const router = useRouter();
  const [vendor, setVendor] = useState<Vendor | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const vendorData = localStorage.getItem("vendor");
    if (!vendorData) {
      router.push("/vendor/login");
      return;
    }

    try {
      const parsed = JSON.parse(vendorData);
      setVendor(parsed);
    } catch (err) {
      console.error(err);
      router.push("/vendor/login");
    } finally {
      setLoading(false);
    }
  }, [router]);

  const handleLogout = () => {
    localStorage.removeItem("vendor");
    router.push("/vendor/login");
  };

  if (loading) {
    return (
      <div style={{
        minHeight: "calc(100vh - 80px)",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
      }}>
        <div style={{ textAlign: "center" }}>
          <div style={{
            width: "60px",
            height: "60px",
            border: "4px solid #f3f4f6",
            borderTop: "4px solid #4169e1",
            borderRadius: "50%",
            animation: "spin 1s linear infinite",
            margin: "0 auto 1rem",
          }} />
          <p style={{ color: "#666", fontSize: "0.9rem" }}>Loading dashboard...</p>
        </div>
        <style jsx>{`
          @keyframes spin {
            to { transform: rotate(360deg); }
          }
        `}</style>
      </div>
    );
  }

  if (!vendor) return null;

  // Status badge styling
  const getStatusBadge = () => {
    const styles: Record<string, { bg: string; color: string; text: string }> = {
      pending: { bg: "#fef3c7", color: "#92400e", text: "⏳ Pending Approval" },
      approved: { bg: "#d1fae5", color: "#065f46", text: "✓ Approved" },
      rejected: { bg: "#fee2e2", color: "#991b1b", text: "✗ Rejected" },
      suspended: { bg: "#f3f4f6", color: "#374151", text: "⊘ Suspended" },
    };
    const style = styles[vendor.status] || styles.pending;
    return (
      <span style={{
        padding: "0.5rem 1rem",
        background: style.bg,
        color: style.color,
        borderRadius: "8px",
        fontSize: "0.85rem",
        fontWeight: 700,
        textTransform: "uppercase",
        letterSpacing: "0.05em",
      }}>
        {style.text}
      </span>
    );
  };

  return (
    <div style={{ minHeight: "calc(100vh - 80px)", background: "#f8f9fa", padding: "2rem" }}>
      <div style={{ maxWidth: "1200px", margin: "0 auto" }}>
        {/* Header */}
        <div style={{
          background: "#fff",
          borderRadius: "16px",
          padding: "2rem",
          marginBottom: "2rem",
          boxShadow: "0 2px 8px rgba(0,0,0,0.08)",
          display: "flex",
          justifyContent: "space-between",
          alignItems: "center",
          flexWrap: "wrap",
          gap: "1rem",
        }}>
          <div>
            <h1 style={{ fontSize: "2rem", fontWeight: 800, marginBottom: "0.5rem", color: "#1a1a1a" }}>
              <Store style={{ width: "32px", height: "32px", display: "inline", marginRight: "0.5rem", verticalAlign: "middle" }} />
              {vendor.store_name || "Your Store"}
            </h1>
            <p style={{ color: "#666", fontSize: "0.95rem", marginBottom: "0.8rem" }}>
              Welcome back, <strong>{vendor.name}</strong>!
            </p>
            {getStatusBadge()}
          </div>
          <button
            onClick={handleLogout}
            style={{
              padding: "0.8rem 1.5rem",
              background: "#ef4444",
              color: "#fff",
              border: "none",
              borderRadius: "10px",
              fontSize: "0.9rem",
              fontWeight: 700,
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              gap: "0.5rem",
              transition: "all 0.2s",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.background = "#dc2626";
              e.currentTarget.style.transform = "translateY(-2px)";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.background = "#ef4444";
              e.currentTarget.style.transform = "translateY(0)";
            }}
          >
            <LogOut style={{ width: "18px", height: "18px" }} />
            Logout
          </button>
        </div>

        {/* Status Message */}
        {vendor.status === "pending" && (
          <div style={{
            background: "#fffbeb",
            border: "2px solid #fbbf24",
            borderRadius: "12px",
            padding: "1.5rem",
            marginBottom: "2rem",
            display: "flex",
            alignItems: "start",
            gap: "1rem",
          }}>
            <div style={{
              width: "48px",
              height: "48px",
              background: "#fbbf24",
              borderRadius: "50%",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              flexShrink: 0,
            }}>
              <Store style={{ width: "24px", height: "24px", color: "#fff" }} />
            </div>
            <div>
              <h3 style={{ fontSize: "1.1rem", fontWeight: 700, marginBottom: "0.5rem", color: "#92400e" }}>
                Application Under Review
              </h3>
              <p style={{ color: "#92400e", fontSize: "0.9rem", lineHeight: 1.6 }}>
                Your store application is currently being reviewed by our admin team. 
                You'll receive an email notification once your store is approved. 
                This usually takes 24-48 hours.
              </p>
            </div>
          </div>
        )}

        {vendor.status === "rejected" && (
          <div style={{
            background: "#fee2e2",
            border: "2px solid #ef4444",
            borderRadius: "12px",
            padding: "1.5rem",
            marginBottom: "2rem",
          }}>
            <h3 style={{ fontSize: "1.1rem", fontWeight: 700, marginBottom: "0.5rem", color: "#991b1b" }}>
              Application Rejected
            </h3>
            <p style={{ color: "#991b1b", fontSize: "0.9rem", lineHeight: 1.6 }}>
              Unfortunately, your store application was not approved. 
              Please contact support for more information or to reapply.
            </p>
          </div>
        )}

        {vendor.status === "suspended" && (
          <div style={{
            background: "#f3f4f6",
            border: "2px solid #9ca3af",
            borderRadius: "12px",
            padding: "1.5rem",
            marginBottom: "2rem",
          }}>
            <h3 style={{ fontSize: "1.1rem", fontWeight: 700, marginBottom: "0.5rem", color: "#374151" }}>
              Store Suspended
            </h3>
            <p style={{ color: "#374151", fontSize: "0.9rem", lineHeight: 1.6 }}>
              Your store has been temporarily suspended. 
              Please contact admin support to resolve this issue.
            </p>
          </div>
        )}

        {/* Stats Cards - Only for Approved */}
        {vendor.status === "approved" && (
          <>
            <div style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(250px, 1fr))",
              gap: "1.5rem",
              marginBottom: "2rem",
            }}>
              <StatCard
                icon={<Package />}
                title="Total Products"
                value="0"
                color="#4169e1"
              />
              <StatCard
                icon={<ShoppingBag />}
                title="Orders Today"
                value="0"
                color="#10b981"
              />
              <StatCard
                icon={<TrendingUp />}
                title="Total Revenue"
                value="Rs. 0"
                color="#f59e0b"
              />
            </div>

            {/* Quick Actions */}
            <div style={{
              background: "#fff",
              borderRadius: "16px",
              padding: "2rem",
              boxShadow: "0 2px 8px rgba(0,0,0,0.08)",
            }}>
              <h2 style={{ fontSize: "1.5rem", fontWeight: 700, marginBottom: "1.5rem", color: "#1a1a1a" }}>
                Quick Actions
              </h2>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: "1rem" }}>
                <ActionButton
                  icon={<Package />}
                  title="Manage Products"
                  description="Add or edit your menu items"
                  onClick={() => alert("Products management coming soon!")}
                />
                <ActionButton
                  icon={<ShoppingBag />}
                  title="View Orders"
                  description="Check new customer orders"
                  onClick={() => alert("Order management coming soon!")}
                />
                <ActionButton
                  icon={<Settings />}
                  title="Store Settings"
                  description="Update store information"
                  onClick={() => alert("Settings coming soon!")}
                />
              </div>
            </div>
          </>
        )}

        {/* Profile Card */}
        <div style={{
          background: "#fff",
          borderRadius: "16px",
          padding: "2rem",
          marginTop: "2rem",
          boxShadow: "0 2px 8px rgba(0,0,0,0.08)",
        }}>
          <h2 style={{ fontSize: "1.5rem", fontWeight: 700, marginBottom: "1.5rem", color: "#1a1a1a" }}>
            <User style={{ width: "24px", height: "24px", display: "inline", marginRight: "0.5rem", verticalAlign: "middle" }} />
            Your Profile
          </h2>
          <div style={{ display: "grid", gap: "1rem" }}>
            <ProfileField label="Name" value={vendor.name} />
            <ProfileField label="Email" value={vendor.email} />
            <ProfileField label="Phone" value={vendor.phone} />
            <ProfileField label="Store Name" value={vendor.store_name || "N/A"} />
          </div>
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon, title, value, color }: { icon: React.ReactNode; title: string; value: string; color: string }) {
  return (
    <div style={{
      background: "#fff",
      borderRadius: "16px",
      padding: "1.5rem",
      boxShadow: "0 2px 8px rgba(0,0,0,0.08)",
      display: "flex",
      alignItems: "center",
      gap: "1rem",
    }}>
      <div style={{
        width: "60px",
        height: "60px",
        background: color,
        borderRadius: "12px",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        color: "#fff",
        flexShrink: 0,
      }}>
        {icon}
      </div>
      <div>
        <p style={{ fontSize: "0.8rem", color: "#666", marginBottom: "0.3rem", textTransform: "uppercase", letterSpacing: "0.05em" }}>
          {title}
        </p>
        <p style={{ fontSize: "1.8rem", fontWeight: 800, color: "#1a1a1a" }}>
          {value}
        </p>
      </div>
    </div>
  );
}

function ActionButton({ icon, title, description, onClick }: { icon: React.ReactNode; title: string; description: string; onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      style={{
        background: "#f8f9fa",
        border: "2px solid rgba(0,0,0,0.06)",
        borderRadius: "12px",
        padding: "1.5rem",
        textAlign: "left",
        cursor: "pointer",
        transition: "all 0.2s",
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.background = "#fff";
        e.currentTarget.style.borderColor = "#4169e1";
        e.currentTarget.style.transform = "translateY(-4px)";
        e.currentTarget.style.boxShadow = "0 8px 16px rgba(65,105,225,0.15)";
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.background = "#f8f9fa";
        e.currentTarget.style.borderColor = "rgba(0,0,0,0.06)";
        e.currentTarget.style.transform = "translateY(0)";
        e.currentTarget.style.boxShadow = "none";
      }}
    >
      <div style={{ color: "#4169e1", marginBottom: "0.8rem" }}>
        {icon}
      </div>
      <h3 style={{ fontSize: "1rem", fontWeight: 700, marginBottom: "0.3rem", color: "#1a1a1a" }}>
        {title}
      </h3>
      <p style={{ fontSize: "0.85rem", color: "#666", lineHeight: 1.5 }}>
        {description}
      </p>
    </button>
  );
}

function ProfileField({ label, value }: { label: string; value: string }) {
  return (
    <div style={{
      display: "grid",
      gridTemplateColumns: "150px 1fr",
      gap: "1rem",
      padding: "1rem",
      background: "#f8f9fa",
      borderRadius: "8px",
    }}>
      <span style={{ fontSize: "0.85rem", color: "#666", fontWeight: 600 }}>
        {label}:
      </span>
      <span style={{ fontSize: "0.9rem", color: "#1a1a1a", fontWeight: 500 }}>
        {value}
      </span>
    </div>
  );
}
