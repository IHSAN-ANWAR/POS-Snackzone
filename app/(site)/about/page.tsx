"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import Image from "next/image";
import { MapPin, Phone, Clock, ChevronRight } from "lucide-react";
import { API_BASE } from "@/lib/api";

const stats = [
  { value: "5.0", label: "Google Rating" },
  { value: "3PM", label: "Opens Daily" },
  { value: "9+",  label: "Menu Items" },
  { value: "1",   label: "Location" },
];

const pillars = [
  { title: "Fresh Ingredients", desc: "Every order is made with fresh, quality ingredients. No pre-packed, no shortcuts — ever." },
  { title: "Made to Order",     desc: "We prepare everything fresh the moment you order. Nothing sits under a heat lamp." },
  { title: "5.0 on Google",     desc: "Rated 5.0 stars on Google by the real people of Rawalpindi. The food speaks for itself." },
];

function imgSrc(image_path: string): string {
  if (!image_path) return "";
  return API_BASE.replace(/\/Admin\/?$/, "") + "/" + image_path;
}

export default function AboutPage() {
  const [categories, setCategories] = useState<{ id: number; name: string; slug: string }[]>([]);
  const [menuItems,  setMenuItems]  = useState<{ id: number; category: string; title: string; image_path: string }[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      fetch(`${API_BASE}/action.php?action=get_categories`, { cache: "no-store" }).then(r => r.json()),
      fetch(`${API_BASE}/get_menu.php`, { cache: "no-store" }).then(r => r.json()),
    ]).then(([catData, menuData]) => {
      if (catData.success && catData.categories) setCategories(catData.categories);
      if (menuData.success && menuData.items)    setMenuItems(menuData.items);
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  // Get first item image for a category slug
  const getCatImage = (slug: string): string => {
    const item = menuItems.find(i => i.category === slug);
    return item ? imgSrc(item.image_path) : "";
  };

  return (
    <div className="bg-white">

      {/* Header */}
      <div className="bg-black py-6 px-5">
        <div className="custom-container">
          <p className="text-xs font-bold tracking-widest text-white/50 uppercase mb-1">Our Story</p>
          <h1
            className="font-black text-white leading-none"
            style={{ fontSize: "clamp(2rem, 6vw, 4rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}
          >
            About Snack Zone
          </h1>
        </div>
      </div>

      {/* Marquee */}
      <div className="bg-white overflow-hidden" style={{ borderBottom: "1px solid rgba(0,0,0,0.08)" }}>
        <div className="flex animate-marquee whitespace-nowrap py-2.5" style={{ width: "max-content" }}>
          {["Fresh Ingredients", "Made to Order", "5.0 Google Rating", "Satellite Town Rawalpindi", "Open 3PM Daily", "Best Fast Food in RWP",
            "Fresh Ingredients", "Made to Order", "5.0 Google Rating", "Satellite Town Rawalpindi", "Open 3PM Daily", "Best Fast Food in RWP",
          ].map((item, i) => (
            <span key={i} className="text-black font-black uppercase text-xs px-8 flex items-center gap-3" style={{ letterSpacing: "0.1em" }}>
              <span className="w-1 h-1 rounded-full bg-black/30 shrink-0" />
              {item}
            </span>
          ))}
        </div>
      </div>

      {/* Story section */}
      <section className="py-16 px-5" style={{ borderBottom: "1px solid rgba(0,0,0,0.07)" }}>
        <div className="custom-container">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
              <p
                className="font-black leading-none mb-8"
                style={{ fontSize: "clamp(2.2rem, 6vw, 5rem)", textTransform: "uppercase", letterSpacing: "-0.02em", color: "#000" }}
              >
                Fast Food Spot
              </p>
              <p className="text-[#000] text-base leading-relaxed mb-4">
                Snack Zone is a fast food court in Satellite Town, Rawalpindi — right opposite Noor Mobile Plaza on 6th Road.
                We started with a simple idea: give the people of Rawalpindi great-tasting fast food at fair prices.
              </p>
              <p className="text-[#000] text-base leading-relaxed mb-8">
                Whether you are craving a juicy Zinger Burger, a perfectly rolled Shawarma, or golden crispy Fries — we have got you covered.
                We take pride in fresh ingredients and preparing every order with care. Rated <strong className="text-black">5.0 on Google Reviews</strong>.
                Come visit us — we open at 3pm daily.
              </p>

              <div className="flex flex-col gap-3">
                {[
                  { icon: MapPin,  text: "6th Rd, opposite Noor Mobile Plaza, Satellite Town, Rawalpindi 43600" },
                  { icon: Phone,   text: "Contact via WhatsApp" },
                  { icon: Clock,   text: "Opens 3pm daily — 7 days a week" },
                ].map(({ icon: Icon, text }, i) => (
                  <div key={i} className="flex items-start gap-3">
                    <div className="w-8 h-8 bg-black flex items-center justify-center shrink-0 mt-0.5">
                      <Icon className="w-3.5 h-3.5 text-white" />
                    </div>
                    <p className="text-sm text-[#444] leading-relaxed">{text}</p>
                  </div>
                ))}
              </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 gap-4">
              {stats.map((s, i) => (
                <div key={i} className="bg-black p-6 flex flex-col justify-between" style={{ minHeight: "120px" }}>
                  <p className="font-black text-white leading-none mb-1" style={{ fontSize: "clamp(2rem, 5vw, 3.5rem)", letterSpacing: "-0.02em" }}>
                    {s.value}
                  </p>
                  <p className="text-white/40 text-xs uppercase font-bold tracking-widest">{s.label}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Pillars */}
      <section className="py-14 px-5 bg-white" style={{ borderBottom: "1px solid rgba(0,0,0,0.07)" }}>
        <div className="custom-container">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {pillars.map((p, i) => (
              <div key={i} className="flex flex-col gap-3">
                <div className="w-10 h-10 bg-black text-white font-black flex items-center justify-center mx-auto mb-5 text-xl">
                  {i + 1}
                </div>
                <h3 className="font-black text-black text-center uppercase" style={{ fontSize: "0.9rem", letterSpacing: "0.06em" }}>
                  {p.title}
                </h3>
                <p className="text-[#666] text-sm text-center leading-relaxed">{p.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Dynamic categories from admin panel */}
      <section className="py-16 px-5 bg-white">
        <div className="custom-container">
          <div className="text-center mb-10">
            <h2 className="font-black text-black" style={{ fontSize: "clamp(1.8rem, 4vw, 3rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
              Fresh, Crispy &amp; Full of Flavour
            </h2>
          </div>

          {/* Loading skeleton */}
          {loading && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10">
              {[1, 2, 3].map(i => (
                <div key={i} className="category-card animate-pulse">
                  <div style={{ height: "160px", background: "#f0f0f0" }} />
                  <div className="px-5 pb-5 pt-4">
                    <div style={{ height: "14px", background: "#f0f0f0", width: "60%", margin: "0 auto 8px" }} />
                    <div style={{ height: "10px", background: "#f0f0f0", width: "80%", margin: "0 auto" }} />
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Categories from API */}
          {!loading && categories.length > 0 && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10">
              {categories.map(cat => {
                const img = getCatImage(cat.slug);
                return (
                  <Link key={cat.id} href="/menu" className="category-card group">
                    <div style={{ position: "relative", height: "180px", background: "#000", overflow: "hidden" }}>
                      {img ? (
                        <Image
                          src={img} alt={cat.name} fill
                          sizes="(max-width:640px) 100vw, 33vw"
                          className="object-cover group-hover:scale-105 transition-transform duration-300"
                          unoptimized
                        />
                      ) : (
                        <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center" }}>
                          <span style={{ fontSize: "4rem", fontWeight: 900, color: "#fff", textTransform: "uppercase" }}>
                            {cat.name.charAt(0)}
                          </span>
                        </div>
                      )}
                    </div>
                    <div className="px-5 pb-5 pt-4 text-center">
                      <h3 className="font-black text-black"
                        style={{ fontSize: "1.1rem", textTransform: "uppercase", letterSpacing: "0.02em" }}>
                        {cat.name}
                      </h3>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}

          {/* Fallback if no categories */}
          {!loading && categories.length === 0 && (
            <p className="text-center text-[#aaa] text-sm mb-10">
              No categories yet — add them from the Admin Panel.
            </p>
          )}

          <div className="text-center">
            <Link href="/menu" className="btn-black inline-flex items-center gap-2">
              View Full Menu <ChevronRight className="w-4 h-4" />
            </Link>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="bg-black py-14 px-5">
        <div className="custom-container text-center">
          <h2 className="font-black text-white mb-3"
            style={{ fontSize: "clamp(1.8rem, 4vw, 3rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
            Come Try It Yourself
          </h2>
          <p className="text-white/50 text-sm mb-8 max-w-md mx-auto">
            Walk in to Satellite Town, 6th Road — opposite Noor Mobile Plaza. Opens 3pm daily.
          </p>
          <div className="flex flex-wrap gap-4 justify-center">
            <Link href="/location" className="btn-white">Find Us</Link>
            <Link href="/contact" className="btn-white"
              style={{ background: "transparent", borderColor: "rgba(255,255,255,0.3)", color: "#fff" }}>
              Contact Us
            </Link>
          </div>
        </div>
      </section>

    </div>
  );
}
