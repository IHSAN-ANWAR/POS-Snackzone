"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { MapPin, Phone, MessageCircle, Clock, Mail } from "lucide-react";
import ContactForm from "./ContactForm";
import { API_BASE } from "@/lib/api";

export default function ContactPage() {
  const [address, setAddress] = useState("6th Rd, opposite Noor Mobile Plaza, Satellite Town, Rawalpindi, 43600");
  const [phone, setPhone] = useState("");
  const [whatsapp, setWhatsapp] = useState("923106653966");

  useEffect(() => {
    fetch(`${API_BASE}/action.php?action=get_site_settings`)
      .then(r => r.json())
      .then(data => {
        if (data?.success) {
          if (data.site_address) setAddress(data.site_address);
          if (data.site_phone) setPhone(data.site_phone);
          if (data.whatsapp_number) setWhatsapp(data.whatsapp_number);
        }
      })
      .catch(() => {});
  }, []);

  const contactCards = [
    {
      icon: MapPin,
      title: "Visit Us",
      value: address,
      sub: "Main location",
      link: `https://maps.google.com/?q=${encodeURIComponent(address)}`,
      linkLabel: "Get Directions",
      external: true,
    },
    {
      icon: Phone,
      title: "Call Us",
      value: phone,
      sub: "For orders & enquiries",
      link: `tel:${phone.replace(/\s/g, "")}`,
      linkLabel: "Call Now",
      external: false,
    },
    {
      icon: MessageCircle,
      title: "WhatsApp",
      value: "Chat with Us",
      sub: "Quick replies, Mon–Sun",
      link: `https://wa.me/${whatsapp}`,
      linkLabel: "Open WhatsApp",
      external: true,
    },
    {
      icon: Clock,
      title: "Opening Hours",
      value: "3:00 PM — 3:00 AM",
      sub: "7 days a week",
      link: null,
      linkLabel: null,
      external: false,
    },
    {
      icon: Mail,
      title: "Email",
      value: "info@snackzone.pk",
      sub: "We reply within 24 hours",
      link: "mailto:info@snackzone.pk",
      linkLabel: "Send Email",
      external: false,
    },
  ];
  return (
    <div className="bg-white">

      {/* ── PAGE HEADING BAR ── */}
      <div className="bg-black py-6 px-5">
        <div className="custom-container">
          <p className="text-xs font-bold tracking-widest text-white/50 uppercase mb-1">Get in Touch</p>
          <h1
            className="font-black text-white leading-none"
            style={{ fontSize: "clamp(2rem, 6vw, 4rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}
          >
            Contact Us
          </h1>
        </div>
      </div>

      {/* ── CONTACT CARDS ── */}
      <section className="py-14 px-5" style={{ borderBottom: "1px solid rgba(0,0,0,0.07)" }}>
        <div className="custom-container">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {contactCards.map((card) => (
              <div
                key={card.title}
                className="p-6 flex flex-col gap-3"
                style={{ border: "1px solid rgba(0,0,0,0.1)", borderRadius: "4px" }}
              >
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-black flex items-center justify-center rounded shrink-0">
                    <card.icon className="w-5 h-5 text-white" />
                  </div>
                  <p className="font-black text-black text-xs uppercase tracking-widest">
                    {card.title}
                  </p>
                </div>
                <div>
                  <p className="font-bold text-black text-sm">{card.value}</p>
                  <p className="text-[#888] text-xs mt-0.5">{card.sub}</p>
                </div>
                {card.link && card.linkLabel && (
                  <a
                    href={card.link}
                    target={card.external ? "_blank" : undefined}
                    rel={card.external ? "noopener noreferrer" : undefined}
                    className="btn-black text-xs py-2 px-4 self-start"
                  >
                    {card.linkLabel}
                  </a>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── FORM + MAP ── */}
      <section className="py-14 px-5">
        <div className="custom-container">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">

            {/* Form — client component */}
            <div style={{ border: "1px solid rgba(0,0,0,0.1)", borderRadius: "4px", padding: "32px" }}>
              <ContactForm />
            </div>

            {/* Map + info */}
            <div className="flex flex-col gap-5">
              <div className="overflow-hidden" style={{ border: "1px solid rgba(0,0,0,0.1)", borderRadius: "4px" }}>
                <iframe
                  src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3323.8!2d73.0485!3d33.6307!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x38df96279e26b475%3A0x1c1e02e77b527ee6!2sSatellite%20Town%2C%20Rawalpindi!5e0!3m2!1sen!2s!4v1234567890"
                  width="100%"
                  height="300"
                  style={{ border: 0, display: "block" }}
                  allowFullScreen
                  loading="lazy"
                  referrerPolicy="no-referrer-when-downgrade"
                  title="Snack Zone Map"
                />
              </div>

              {/* Address card */}
              <div className="p-6 bg-black" style={{ borderRadius: "4px" }}>
                <h3
                  className="font-black text-white mb-4"
                  style={{ fontSize: "0.9rem", textTransform: "uppercase", letterSpacing: "0.06em" }}
                >
                  Main Branch
                </h3>
                <ul className="space-y-3">
                  <li className="flex items-start gap-3">
                    <MapPin className="w-4 h-4 text-white/50 mt-0.5 shrink-0" />
                    <span className="text-sm text-white/60">{address}</span>
                  </li>
                  <li className="flex items-start gap-3">
                    <Phone className="w-4 h-4 text-white/50 mt-0.5 shrink-0" />
                    <a href={`tel:${phone.replace(/\s/g, "")}`} className="text-sm text-white/60 hover:text-white transition-colors">
                      {phone}
                    </a>
                  </li>
                  <li className="flex items-start gap-3">
                    <Clock className="w-4 h-4 text-white/50 mt-0.5 shrink-0" />
                    <span className="text-sm text-white/60">Opens 3:00 PM — 3:00 AM daily</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── CTA ── */}
      <section className="bg-black py-14 px-5">
        <div className="custom-container text-center">
          <h2
            className="font-black text-white mb-3"
            style={{ fontSize: "clamp(1.6rem, 3vw, 2.5rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}
          >
            Or Just Walk In!
          </h2>
          <p className="text-white/50 text-sm mb-6 max-w-sm mx-auto">
            We open at 3pm daily. {address.split(',').slice(-2).join(',')}
          </p>
          <Link href="/location" className="btn-white">
            See All Locations
          </Link>
        </div>
      </section>

    </div>
  );
}
