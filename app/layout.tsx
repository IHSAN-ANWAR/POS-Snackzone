import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700", "800", "900"],
  display: "swap",
});

export const metadata: Metadata = {
  title: "Snack Zone — Rawalpindi ka Favourite Fast Food",
  description:
    "Snack Zone is Rawalpindi's best fast food spot in Satellite Town — Zinger Burgers, Crispy Fries & Shawarma. Opens 3pm daily. Rated 5.0 on Google.",
  keywords: ["Snack Zone", "fast food Rawalpindi", "zinger burger", "shawarma", "Satellite Town"],
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={inter.className} suppressHydrationWarning>
      <body
        className="min-h-screen antialiased overflow-x-hidden"
        style={{ background: "#ffffff", color: "#000000" }}
        suppressHydrationWarning
      >
        {children}
      </body>
    </html>
  );
}
