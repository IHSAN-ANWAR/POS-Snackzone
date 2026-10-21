import Link from "next/link";
import { MapPin, Phone, Clock, Car, Bike, Navigation } from "lucide-react";

const locations = [
  {
    name: "Satellite Town, 6th Road",
    address: "6th Rd, opposite Noor Mobile Plaza, Satellite Town, Rawalpindi 43600",
    phone: "",
    hours: "Opens 3:00 PM — 7 Days a Week",
    map: "https://maps.google.com/?q=6th+Road+opposite+Noor+Mobile+Plaza+Satellite+Town+Rawalpindi",
  },
];

const howToFind = [
  { icon: Car,  title: "By Car",             desc: "Located on 6th Road, Satellite Town — ample parking available right opposite Noor Mobile Plaza." },
  { icon: Bike, title: "Delivery Available", desc: "Order online and we will deliver fresh and hot right to your doorstep." },
  { icon: Clock, title: "Open Daily",        desc: "We open at 3pm every single day — perfect for lunch, dinner, or a late-night snack run." },
];

export default function LocationPage() {
  return (
    <div className="bg-white">

      {/* PAGE HEADING */}
      <div className="bg-black py-6 px-5">
        <div className="custom-container">
          <p className="text-xs font-bold tracking-widest text-white/50 uppercase mb-1">Find Us</p>
          <h1 className="font-black text-white leading-none"
            style={{ fontSize: "clamp(2rem, 6vw, 4rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
            Our Locations
          </h1>
        </div>
      </div>

      {/* QUICK INFO STRIP */}
      <div className="bg-white py-5 px-5" style={{ borderBottom: "2px solid #000" }}>
        <div className="custom-container">
          <div className="flex flex-wrap gap-6 items-center justify-between">
            <div className="flex items-center gap-3">
              <MapPin className="w-4 h-4 text-black shrink-0" />
              <span className="text-sm font-semibold text-black">6th Rd, opposite Noor Mobile Plaza, Satellite Town, Rawalpindi</span>
            </div>
            <div className="flex items-center gap-3">
              <Phone className="w-4 h-4 text-black shrink-0" />
              <span className="text-sm font-semibold text-black">Contact via WhatsApp</span>
            </div>
            <div className="flex items-center gap-3">
              <Clock className="w-4 h-4 text-black shrink-0" />
              <span className="text-sm font-semibold text-black">Opens 3pm Daily — 7 Days a Week</span>
            </div>
            <a href="https://maps.google.com/?q=6th+Road+opposite+Noor+Mobile+Plaza+Satellite+Town+Rawalpindi"
              target="_blank" rel="noopener noreferrer"
              className="btn-black text-xs py-2 px-5 inline-flex items-center gap-2">
              <Navigation className="w-3.5 h-3.5" /> Get Directions
            </a>
          </div>
        </div>
      </div>

      {/* LOCATIONS GRID */}
      <section className="py-14 px-5" style={{ borderBottom: "1px solid rgba(0,0,0,0.07)" }}>
        <div className="custom-container">
          <div className="text-center mb-10">
            <h2 className="font-black text-black"
              style={{ fontSize: "clamp(1.8rem, 4vw, 2.8rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
              Locations
            </h2>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {locations.map((loc) => (
              <div key={loc.name} className="location-card">
                <div>
                  <h3 className="font-black text-white text-center mb-2"
                    style={{ fontSize: "1rem", textTransform: "uppercase", letterSpacing: "0.04em" }}>
                    {loc.name}
                  </h3>
                  <div className="space-y-2 mb-4">
                    <p className="flex items-start gap-2 text-xs text-white/50 justify-center text-center">{loc.address}</p>
                    <p className="flex items-center gap-2 text-xs text-white/50 justify-center">
                      <Phone className="w-3 h-3 shrink-0" />{loc.phone}
                    </p>
                    <p className="flex items-center gap-2 text-xs text-white/50 justify-center">
                      <Clock className="w-3 h-3 shrink-0" />{loc.hours}
                    </p>
                  </div>
                </div>
                <div className="flex justify-center">
                  <a href={loc.map} target="_blank" rel="noopener noreferrer" className="view-location-btn">
                    View Location
                  </a>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* MAP EMBED */}
      <section className="py-0">
        <iframe
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3323.8!2d73.0485!3d33.6307!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x38df96279e26b475%3A0x1c1e02e77b527ee6!2sSatellite%20Town%2C%20Rawalpindi%2C%20Punjab%2C%20Pakistan!5e0!3m2!1sen!2s!4v1234567890"
          width="100%" height="420"
          style={{ border: 0, display: "block" }}
          allowFullScreen loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
          title="Snack Zone Location Map"
        />
      </section>

      {/* HOW TO FIND */}
      <section className="py-14 px-5 bg-white" style={{ borderBottom: "1px solid rgba(0,0,0,0.07)" }}>
        <div className="custom-container">
          <div className="text-center mb-10">
            <h2 className="font-black text-black"
              style={{ fontSize: "clamp(1.8rem, 4vw, 2.8rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
              Getting Here
            </h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {howToFind.map((item) => (
              <div key={item.title} className="p-8 text-center"
                style={{ border: "1px solid rgba(0,0,0,0.1)", borderRadius: "4px" }}>
                <div className="w-12 h-12 bg-black flex items-center justify-center mx-auto mb-5 rounded">
                  <item.icon className="w-6 h-6 text-white" />
                </div>
                <h3 className="font-black text-black mb-3"
                  style={{ fontSize: "1rem", textTransform: "uppercase", letterSpacing: "0.04em" }}>
                  {item.title}
                </h3>
                <p className="text-[#777] text-sm leading-relaxed">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="bg-black py-14 px-5">
        <div className="custom-container text-center">
          <h2 className="font-black text-white mb-3"
            style={{ fontSize: "clamp(1.6rem, 3vw, 2.5rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}>
            See You Soon!
          </h2>
          <p className="text-white/50 text-sm mb-6 max-w-sm mx-auto">
            We open at 3pm daily. Walk in or call ahead — we are always ready.
          </p>
          <Link href="/contact" className="btn-white">Get in Touch</Link>
        </div>
      </section>

    </div>
  );
}
