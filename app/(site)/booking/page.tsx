"use client";

import { useState } from "react";
import Link from "next/link";
import {
  User, Phone, Mail, CalendarDays, Clock, Users,
  UtensilsCrossed, ClipboardList, CheckCircle,
  Loader2, ArrowLeft, PartyPopper,
} from "lucide-react";
import { postForm } from "@/lib/api";

const whyBook = [
  { title: "Bulk Orders Welcome", desc: "From 20 to 2000 guests — we scale up to make sure everyone gets fed and happy." },
  { title: "Fresh, Every Time",   desc: "We cook everything fresh on the day. No pre-made trays, no compromises on quality." },
  { title: "Flexible Menu",       desc: "Choose exactly what you want from our menu and we will put together the perfect spread." },
];

interface FormFields {
  name: string;
  phone: string;
  email: string;
  date: string;
  time: string;
  guests: string;
  menuItems: string;
  notes: string;
}

type FormErrors = Partial<Record<keyof FormFields, string>>;

function validate(form: FormFields): FormErrors {
  const errors: FormErrors = {};

  if (!form.name.trim())
    errors.name = "Full name is required.";
  else if (form.name.trim().length < 2)
    errors.name = "Name must be at least 2 characters.";

  if (!form.phone.trim())
    errors.phone = "Phone number is required.";
  else if (!/^[0-9+\s\-]{7,15}$/.test(form.phone.trim()))
    errors.phone = "Enter a valid phone number.";

  if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()))
    errors.email = "Enter a valid email address.";

  if (!form.date)
    errors.date = "Event date is required.";
  else if (new Date(form.date) < new Date(new Date().toDateString()))
    errors.date = "Date cannot be in the past.";

  if (!form.time)
    errors.time = "Event time is required.";

  if (!form.guests.trim())
    errors.guests = "Number of guests is required.";
  else if (isNaN(Number(form.guests)) || Number(form.guests) < 1)
    errors.guests = "Enter a valid number (minimum 1).";
  else if (Number(form.guests) > 5000)
    errors.guests = "For orders over 5000 guests, please call us directly.";

  if (!form.menuItems.trim())
    errors.menuItems = "Please describe which menu items you need.";
  else if (form.menuItems.trim().length < 5)
    errors.menuItems = "Please give a bit more detail on the menu items.";

  return errors;
}

function FieldError({ msg }: { msg?: string }) {
  if (!msg) return null;
  return <p className="text-[#e63946] text-xs mt-1.5 font-medium">{msg}</p>;
}

export default function BookingPage() {
  const [form, setForm] = useState<FormFields>({
    name: "", phone: "", email: "",
    date: "", time: "", guests: "", menuItems: "", notes: "",
  });
  const [errors, setErrors] = useState<FormErrors>({});
  const [touched, setTouched] = useState<Partial<Record<keyof FormFields, boolean>>>({});
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading]     = useState(false);
  const [serverError, setServerError] = useState("");

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    const updated = { ...form, [name]: value };
    setForm(updated);
    if (touched[name as keyof FormFields]) {
      setErrors(validate(updated));
    }
  };

  const handleBlur = (e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const name = e.target.name as keyof FormFields;
    setTouched((t) => ({ ...t, [name]: true }));
    setErrors(validate(form));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerError("");
    const allTouched = Object.fromEntries(
      Object.keys(form).map((k) => [k, true])
    ) as Record<keyof FormFields, boolean>;
    setTouched(allTouched);

    const errs = validate(form);
    setErrors(errs);
    if (Object.keys(errs).length > 0) return;

    setLoading(true);
    try {
      const res = await postForm("save_booking.php", {
        name:       form.name,
        phone:      form.phone,
        email:      form.email,
        event_date: form.date,
        event_time: form.time,
        guests:     form.guests,
        menu_items: form.menuItems,
        notes:      form.notes,
      });
      if (res.success) {
        setSubmitted(true);
      } else {
        setServerError(res.message ?? "Something went wrong. Please try again.");
      }
    } catch {
      setServerError("Could not reach the server. Please check your connection.");
    } finally {
      setLoading(false);
    }
  };

  const inputClass = (field: keyof FormFields) =>
    `contact-input${touched[field] && errors[field] ? " contact-input-error" : ""}`;

  return (
    <div className="bg-white">

      <div className="bg-black py-6 px-5">
        <div className="custom-container">
          <p className="text-xs font-bold tracking-widest text-white/50 uppercase mb-1">Catering &amp; Events</p>
          <h1
            className="font-black text-white leading-none"
            style={{ fontSize: "clamp(2rem, 6vw, 4rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}
          >
            Book Your Event
          </h1>
        </div>
      </div>

      <section className="py-14 px-5" style={{ borderBottom: "1px solid rgba(0,0,0,0.07)" }}>
        <div className="custom-container">
          <div className="text-center mb-10">
            <h2
              className="font-black text-black"
              style={{ fontSize: "clamp(1.6rem, 3.5vw, 2.5rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}
            >
              Catering for Any Occasion
            </h2>
            <p className="text-[#888] text-sm mt-2 max-w-md mx-auto">
              Weddings, corporate lunches, birthday parties, office events — we handle it all.
            </p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {whyBook.map((item, i) => (
              <div key={item.title} className="p-7" style={{ border: "1px solid rgba(0,0,0,0.1)", borderRadius: "4px" }}>
                <div className="w-10 h-10 bg-black text-white font-black flex items-center justify-center rounded mb-4 text-sm">
                  {String(i + 1).padStart(2, "0")}
                </div>
                <h3 className="font-black text-black mb-2" style={{ fontSize: "0.95rem", textTransform: "uppercase", letterSpacing: "0.04em" }}>
                  {item.title}
                </h3>
                <p className="text-[#777] text-sm leading-relaxed">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="py-14 px-5">
        <div className="custom-container">
          <div style={{ maxWidth: "760px", margin: "0 auto", border: "1px solid rgba(0,0,0,0.1)", borderRadius: "4px", padding: "40px" }}>
            {submitted ? (
              <div className="text-center py-12 animate-fade-in">
                <div className="w-16 h-16 bg-black flex items-center justify-center mx-auto mb-5 rounded">
                  <PartyPopper className="w-8 h-8 text-white" />
                </div>
                <h2 className="font-black text-black mb-2" style={{ fontSize: "1.6rem", textTransform: "uppercase", letterSpacing: "-0.01em" }}>
                  Booking Request Sent!
                </h2>
                <p className="text-[#888] text-sm mb-8 max-w-sm mx-auto">
                  Thank you! Our team will call you back shortly to confirm your booking details.
                </p>
                <Link href="/" className="btn-black inline-flex items-center gap-2">
                  <ArrowLeft className="w-4 h-4" /> Back to Home
                </Link>
              </div>
            ) : (
              <>
                <div className="flex items-center gap-3 mb-8 pb-6" style={{ borderBottom: "1px solid rgba(0,0,0,0.08)" }}>
                  <div className="w-10 h-10 bg-black flex items-center justify-center rounded shrink-0">
                    <ClipboardList className="w-5 h-5 text-white" />
                  </div>
                  <div>
                    <h2 className="font-black text-black" style={{ fontSize: "1.1rem", textTransform: "uppercase", letterSpacing: "0.02em" }}>
                      Event Booking Form
                    </h2>
                    <p className="text-[#888] text-xs mt-0.5">Fill in the details — we will contact you to confirm.</p>
                  </div>
                </div>

                <form onSubmit={handleSubmit} noValidate className="space-y-7">

                  <fieldset>
                    <legend className="booking-legend">Your Information</legend>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                      <div>
                        <label className="contact-label"><User className="w-3 h-3" /> Full Name</label>
                        <input
                          name="name" value={form.name}
                          onChange={handleChange} onBlur={handleBlur}
                          placeholder="Full Name"
                          className={inputClass("name")}
                        />
                        <FieldError msg={touched.name ? errors.name : undefined} />
                      </div>
                      <div>
                        <label className="contact-label"><Phone className="w-3 h-3" /> Phone Number</label>
                        <input
                          name="phone" value={form.phone}
                          onChange={handleChange} onBlur={handleBlur}
                          placeholder="0310 0000000"
                          className={inputClass("phone")}
                        />
                        <FieldError msg={touched.phone ? errors.phone : undefined} />
                      </div>
                    </div>
                    <div className="mt-4">
                      <label className="contact-label"><Mail className="w-3 h-3" /> Email Address <span className="text-[#bbb] normal-case font-normal">(optional)</span></label>
                      <input
                        name="email" type="email" value={form.email}
                        onChange={handleChange} onBlur={handleBlur}
                        placeholder="you@example.com"
                        className={inputClass("email")}
                      />
                      <FieldError msg={touched.email ? errors.email : undefined} />
                    </div>
                  </fieldset>

                  <fieldset>
                    <legend className="booking-legend">Event Details</legend>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                      <div>
                        <label className="contact-label"><CalendarDays className="w-3 h-3" /> Event Date</label>
                        <input
                          name="date" type="date" value={form.date}
                          onChange={handleChange} onBlur={handleBlur}
                          className={inputClass("date")}
                        />
                        <FieldError msg={touched.date ? errors.date : undefined} />
                      </div>
                      <div>
                        <label className="contact-label"><Clock className="w-3 h-3" /> Event Time</label>
                        <input
                          name="time" type="time" value={form.time}
                          onChange={handleChange} onBlur={handleBlur}
                          className={inputClass("time")}
                        />
                        <FieldError msg={touched.time ? errors.time : undefined} />
                      </div>
                    </div>
                    <div className="mt-4">
                      <label className="contact-label"><Users className="w-3 h-3" /> Number of Guests</label>
                      <input
                        name="guests" type="number" min="1" value={form.guests}
                        onChange={handleChange} onBlur={handleBlur}
                        placeholder="e.g. 50"
                        className={inputClass("guests")}
                      />
                      <FieldError msg={touched.guests ? errors.guests : undefined} />
                    </div>
                  </fieldset>

                  <fieldset>
                    <legend className="booking-legend flex items-center gap-1.5">
                      <UtensilsCrossed className="w-3.5 h-3.5" /> Menu Items Required
                    </legend>
                    <div className="mt-4">
                      <textarea
                        name="menuItems" value={form.menuItems}
                        onChange={handleChange} onBlur={handleBlur}
                        rows={3}
                        placeholder="e.g. 50x Zinger Burger, 30x Chicken Shawarma, 20x Loaded Fries…"
                        className={`${inputClass("menuItems")} resize-none`}
                      />
                      <p className="text-xs text-[#aaa] mt-1">Write whatever items and quantities you need.</p>
                      <FieldError msg={touched.menuItems ? errors.menuItems : undefined} />
                    </div>
                  </fieldset>

                  <div>
                    <label className="contact-label">
                      <ClipboardList className="w-3 h-3" /> Additional Notes <span className="text-[#bbb] normal-case font-normal">(optional)</span>
                    </label>
                    <textarea
                      name="notes" value={form.notes}
                      onChange={handleChange} onBlur={handleBlur}
                      rows={3}
                      placeholder="Special requirements, dietary needs, delivery address…"
                      className="contact-input resize-none"
                    />
                  </div>

                  {Object.keys(errors).length > 0 && Object.keys(touched).length > 0 && (
                    <p className="text-[#e63946] text-xs font-medium text-center">
                      Please fix the errors above before submitting.
                    </p>
                  )}

                  {serverError && (
                    <p className="text-[#e63946] text-xs font-medium bg-red-50 border border-red-100 rounded px-3 py-2">
                      {serverError}
                    </p>
                  )}

                  <button
                    type="submit" disabled={loading}
                    className="btn-black w-full justify-center py-3 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {loading
                      ? <><Loader2 className="w-4 h-4 animate-spin" /> Submitting…</>
                      : <><CheckCircle className="w-4 h-4" /> Submit Booking Request</>
                    }
                  </button>
                </form>
              </>
            )}
          </div>
        </div>
      </section>

      <section className="bg-black py-14 px-5">
        <div className="custom-container text-center">
          <h2
            className="font-black text-white mb-3"
            style={{ fontSize: "clamp(1.6rem, 3vw, 2.5rem)", textTransform: "uppercase", letterSpacing: "-0.02em" }}
          >
            Rather Call Us?
          </h2>
          <p className="text-white/50 text-sm mb-6 max-w-sm mx-auto">
            For urgent bookings just give us a ring — we will sort you out quickly.
          </p>
          <p className="btn-white inline-flex items-center gap-2 cursor-default">
            <Phone className="w-4 h-4" /> Contact via WhatsApp
          </p>
        </div>
      </section>

    </div>
  );
}
