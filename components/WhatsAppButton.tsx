"use client";

import { useEffect, useState } from "react";
import { API_BASE } from "@/lib/api";

const DEFAULT_NUMBER = process.env.NEXT_PUBLIC_WHATSAPP_NUMBER ?? "923045860472";

export default function WhatsAppButton() {
  const [waNumber,  setWaNumber]  = useState(DEFAULT_NUMBER);
  const [enabled,   setEnabled]   = useState(true);

  useEffect(() => {
    fetch(`${API_BASE}/action.php?action=get_site_settings`)
      .then(r => r.json())
      .then(data => {
        if (data?.success) {
          if (data?.whatsapp_number) setWaNumber(data.whatsapp_number);
          if (data?.wa_button_enabled === false) setEnabled(false);
        }
      })
      .catch(() => {});
  }, []);

  // Hide if admin turned it off
  if (!enabled) return null;

  const waUrl = `https://wa.me/${waNumber}?text=${encodeURIComponent(
    "Hi! Snack Zone se order karna chahta/chahti hoon. Please help karain. 🍔"
  )}`;

  return (
    <>
      <style>{`
        .wa-fab {
          position: fixed;
          bottom: 1.5rem;
          right: 1.5rem;
          z-index: 9999;
          width: 70px;
          height: 70px;
          border-radius: 50%;
          background: #25D366;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 8px 28px rgba(37,211,102,0.5);
          text-decoration: none;
          transition: transform 0.2s ease, box-shadow 0.2s ease;
          animation: waPop 0.4s cubic-bezier(0.175,0.885,0.32,1.275) both;
        }
        .wa-fab:hover {
          transform: scale(1.12);
          box-shadow: 0 12px 36px rgba(37,211,102,0.6);
        }
        .wa-fab:active { transform: scale(0.96); }
        .wa-fab::before {
          content: '';
          position: absolute;
          inset: -4px;
          border-radius: 50%;
          border: 3px solid rgba(37,211,102,0.5);
          animation: waPulse 2.2s ease-out infinite;
        }
        @keyframes waPulse {
          0%   { transform: scale(1);    opacity: 0.7; }
          70%  { transform: scale(1.35); opacity: 0; }
          100% { transform: scale(1.35); opacity: 0; }
        }
        @keyframes waPop {
          from { opacity: 0; transform: scale(0.5); }
          to   { opacity: 1; transform: scale(1); }
        }
        .wa-tooltip {
          position: absolute;
          right: calc(100% + 12px);
          bottom: 50%;
          transform: translateY(50%);
          background: #1a1a1a;
          color: #fff;
          font-size: 0.75rem;
          font-weight: 700;
          white-space: nowrap;
          padding: 8px 14px;
          border-radius: 8px;
          pointer-events: none;
          opacity: 0;
          transition: opacity 0.2s;
          font-family: 'Inter', sans-serif;
          letter-spacing: 0.02em;
        }
        .wa-tooltip::before {
          content: '';
          position: absolute;
          left: 100%;
          top: 50%;
          transform: translateY(-50%);
          border: 6px solid transparent;
          border-left-color: #1a1a1a;
        }
        .wa-fab:hover .wa-tooltip { opacity: 1; }
      `}</style>

      <a
        href={waUrl}
        target="_blank"
        rel="noopener noreferrer"
        className="wa-fab"
        aria-label="Chat on WhatsApp"
      >
        <svg viewBox="0 0 32 32" width="36" height="36" fill="none"
          xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M16 2C8.268 2 2 8.268 2 16c0 2.478.65 4.8 1.787 6.81L2 30l7.41-1.764A13.927 13.927 0 0016 30c7.732 0 14-6.268 14-14S23.732 2 16 2z" fill="#fff"/>
          <path d="M22.77 19.48c-.32-.16-1.888-.93-2.18-1.037-.292-.107-.504-.16-.716.16-.213.32-.824 1.037-.99 1.23-.16.194-.32.213-.64.053-.32-.16-1.35-.498-2.572-1.587-.95-.848-1.59-1.895-1.777-2.215-.186-.32-.02-.493.14-.652.144-.143.32-.373.48-.56.16-.186.213-.32.32-.533.107-.213.053-.4-.027-.56-.08-.16-.716-1.727-.98-2.364-.258-.62-.52-.536-.716-.546l-.61-.01c-.213 0-.56.08-.853.4-.293.32-1.12 1.094-1.12 2.667s1.147 3.094 1.307 3.307c.16.213 2.258 3.453 5.474 4.84.766.33 1.363.527 1.83.674.768.244 1.467.21 2.02.127.616-.092 1.888-.773 2.154-1.52.267-.746.267-1.386.187-1.52-.08-.133-.293-.213-.614-.373z" fill="#25D366"/>
        </svg>
        <span className="wa-tooltip">WhatsApp par message karen</span>
      </a>
    </>
  );
}
