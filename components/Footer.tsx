"use client";

import Link from "next/link";
import { MapPin, Phone, Clock, Star } from "lucide-react";
import { useEffect, useState } from "react";

export default function Footer() {
  const [siteName, setSiteName] = useState("SNACKZONE");
  const [address, setAddress] = useState("");
  const [phone, setPhone] = useState("");

  useEffect(() => {
    fetch("/Admin/action.php?action=get_site_settings")
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          setSiteName(data.site_name || "SNACKZONE");
          setAddress(data.site_address || "");
          setPhone(data.site_phone || "");
        }
      })
      .catch(() => {});
  }, []);

  return (
    <>
      <footer style={{ background: "#000", padding: "60px 20px 50px" }}>
        <div className="custom-container">

          {/* Logo only - no tagline */}
          <div className="flex flex-col items-center text-center pb-10 mb-10"
            style={{ borderBottom: "1px solid rgba(255,255,255,0.08)" }}>
            <Link href="/">
              <span className="font-black text-white uppercase"
                style={{ fontSize: "2rem", letterSpacing: "-0.02em" }}>
                {siteName.split("ZONE")[0] || "SNACK"}<span style={{ color: "#e63946" }}>ZONE</span>
              </span>
            </Link>
          </div>

          {/* Contact info + Quick Links */}
          <div className="flex flex-col items-center gap-6">
            {/* Quick Links */}
            <nav>
              <ul className="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
                <li><Link href="/" className="text-white/60 hover:text-white transition-colors">Home</Link></li>
                <li><Link href="/menu" className="text-white/60 hover:text-white transition-colors">Menu</Link></li>
                <li><Link href="/about" className="text-white/60 hover:text-white transition-colors">About</Link></li>
                <li><Link href="/contact" className="text-white/60 hover:text-white transition-colors">Contact</Link></li>
                <li><Link href="/location" className="text-white/60 hover:text-white transition-colors">Location</Link></li>
                <li><Link href="/booking" className="text-white/60 hover:text-white transition-colors">Booking</Link></li>
              </ul>
            </nav>

            {/* Contact Info - Dynamic from Admin */}
            <ul className="flex flex-wrap justify-center gap-x-8 gap-y-3">
              {address && (
                <li className="flex items-center gap-2">
                  <MapPin className="w-3.5 h-3.5 text-white/40 shrink-0" />
                  <span className="footer-contact-value text-xs">{address}</span>
                </li>
              )}
              {phone && (
                <li className="flex items-center gap-2">
                  <Phone className="w-3.5 h-3.5 text-white/40 shrink-0" />
                  <a href={`tel:${phone.replace(/\s/g, "")}`} className="footer-contact-value text-xs">{phone}</a>
                </li>
              )}
              <li className="flex items-center gap-2">
                <Clock className="w-3.5 h-3.5 text-white/40 shrink-0" />
                <span className="footer-contact-value text-xs">Opens 3pm daily</span>
              </li>
              <li className="flex items-center gap-2">
                <Star className="w-3.5 h-3.5 fill-[#ffd60a] text-[#ffd60a] shrink-0" />
                <span className="text-xs font-semibold" style={{ color: "#ffd60a" }}>5.0 Google Reviews</span>
              </li>
            </ul>
          </div>

        </div>
      </footer>

      <div style={{ background: "#000", borderTop: "1px solid rgba(255,255,255,0.08)" }}>
        <div className="custom-container flex flex-col sm:flex-row items-center justify-center gap-2 py-4"
          style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.35)" }}>
          <span>© 2026 {siteName}, Rawalpindi. All Rights Reserved.</span>
        </div>
      </div>
    </>
  );
}
