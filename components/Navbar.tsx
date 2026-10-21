"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useState, useEffect } from "react";
import { ShoppingCart, User, LogOut, LogIn, MapPin } from "lucide-react";
import { getUser, clearUser, type AuthUser } from "@/lib/auth";
import { API_BASE } from "@/lib/api";

export default function Navbar() {
  const pathname = usePathname();
  const router   = useRouter();
  const [scrolled,   setScrolled]   = useState(false);
  const [user,       setUserState]  = useState<AuthUser | null>(null);
  const [showDeliveryBtn, setShowDeliveryBtn] = useState(false);
  const [siteName, setSiteName] = useState("SNACKZONE");

  useEffect(() => { setUserState(getUser()); }, [pathname]);

  // Fetch site name from API
  useEffect(() => {
    fetch(`${API_BASE}/action.php?action=get_site_settings`)
      .then(r => r.json())
      .then(data => {
        if (data?.success && data?.site_name) {
          setSiteName(data.site_name);
        }
      })
      .catch(() => {/* fallback to default */});
  }, []);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 10);
    window.addEventListener("scroll", onScroll);
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Check if delivery modal should be shown (only on homepage)
  useEffect(() => {
    if (pathname === "/") {
      setShowDeliveryBtn(true);
    } else {
      setShowDeliveryBtn(false);
    }
  }, [pathname]);

  const handleLogout = () => {
    clearUser();
    setUserState(null);
    router.push("/");
  };

  const openDeliveryModal = () => {
    // Clear localStorage choice to reopen modal
    localStorage.removeItem("sz_delivery_choice");
    // Reload to trigger modal
    window.location.reload();
  };

  return (
    <header
      className={`sticky top-0 z-50 bg-white transition-shadow duration-300 ${scrolled ? "shadow-[0_4px_20px_rgba(0,0,0,0.12)]" : "shadow-[0_2px_8px_rgba(0,0,0,0.06)]"}`}
      style={{ borderBottom: "2px solid rgba(0,0,0,0.1)" }}
    >
      <nav className="custom-container h-[80px] flex items-center justify-between">

        {/* Logo */}
        <Link href="/" className="flex items-center shrink-0">
          <span className="font-black text-black uppercase" style={{ fontSize: "1.4rem", letterSpacing: "-0.02em" }}>
            {siteName}
          </span>
        </Link>

        {/* Right side — cart + auth only */}
        <div className="flex items-center gap-4">

          {/* Delivery modal trigger — only on homepage */}
          {showDeliveryBtn && (
            <button
              onClick={openDeliveryModal}
              title="Change delivery preference"
              style={{
                width: "48px", height: "48px", background: "transparent",
                border: "2px solid rgba(0,0,0,0.15)", borderRadius: "10px",
                cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center",
                transition: "all 0.2s",
              }}
              onMouseEnter={e => {
                e.currentTarget.style.background = "#f5f5f5";
                e.currentTarget.style.borderColor = "#000";
              }}
              onMouseLeave={e => {
                e.currentTarget.style.background = "transparent";
                e.currentTarget.style.borderColor = "rgba(0,0,0,0.15)";
              }}
            >
              <MapPin className="w-5 h-5" />
            </button>
          )}

          {/* Cart — goes to menu/order */}
          <Link href="/menu" aria-label="Order Now"
            style={{ width: "48px", height: "48px", background: "#000", border: "none",
              borderRadius: "10px",
              cursor: "pointer", display: "flex", alignItems: "center", justifyContent: "center",
              textDecoration: "none", transition: "all 0.2s" }}>
            <ShoppingCart className="w-5 h-5 text-white" />
          </Link>

          {/* Auth — hidden for now (WhatsApp ordering, no login needed) */}
          {/* {user ? (
            <div style={{ display: "flex", alignItems: "center", gap: "10px" }}>
              <Link href="/profile"
                style={{ display: "flex", alignItems: "center", gap: "8px",
                  background: "#000", color: "#fff", padding: "12px 18px",
                  fontSize: "0.8rem", fontWeight: 700, textTransform: "uppercase",
                  letterSpacing: "0.05em", textDecoration: "none", borderRadius: "10px",
                  transition: "all 0.2s" }}>
                <User className="w-4 h-4" />
                {user.name.split(" ")[0]}
              </Link>
              <button onClick={handleLogout}
                style={{ width: "44px", height: "44px", background: "#f5f5f5",
                  border: "2px solid rgba(0,0,0,0.15)", cursor: "pointer",
                  display: "flex", alignItems: "center", justifyContent: "center",
                  borderRadius: "10px", transition: "all 0.2s" }}
                title="Logout">
                <LogOut className="w-4 h-4 text-black" />
              </button>
            </div>
          ) : (
            <Link href="/login" className="btn-black text-sm py-3 px-5"
              style={{ display: "flex", alignItems: "center", gap: "8px", borderRadius: "10px" }}>
              <LogIn className="w-4 h-4" /> Login
            </Link>
          )} */}

        </div>
      </nav>
    </header>
  );
}
