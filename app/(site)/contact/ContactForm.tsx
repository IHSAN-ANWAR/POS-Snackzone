"use client";

import { useState } from "react";
import { Send, Loader2, CheckCircle } from "lucide-react";
import { postForm } from "@/lib/api";

const subjects = [
  "General Enquiry",
  "Order Issue",
  "Feedback / Suggestion",
  "Catering / Bulk Order",
  "Franchise Enquiry",
  "Other",
];

interface Fields {
  name: string;
  phone: string;
  email: string;
  subject: string;
  message: string;
}

type Errors = Partial<Record<keyof Fields, string>>;

function validate(f: Fields): Errors {
  const e: Errors = {};

  if (!f.name.trim())
    e.name = "Name is required.";
  else if (f.name.trim().length < 2)
    e.name = "Name must be at least 2 characters.";

  if (f.phone.trim() && !/^[0-9+\s\-]{7,15}$/.test(f.phone.trim()))
    e.phone = "Enter a valid phone number.";

  if (f.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim()))
    e.email = "Enter a valid email address.";

  if (!f.subject)
    e.subject = "Please select a subject.";

  if (!f.message.trim())
    e.message = "Message is required.";
  else if (f.message.trim().length < 10)
    e.message = "Message is too short — tell us a bit more.";

  return e;
}

function FieldError({ msg }: { msg?: string }) {
  if (!msg) return null;
  return <p className="text-[#e63946] text-xs mt-1.5 font-medium">{msg}</p>;
}

export default function ContactForm() {
  const blank: Fields = { name: "", phone: "", email: "", subject: "", message: "" };

  const [form, setForm]         = useState<Fields>(blank);
  const [errors, setErrors]     = useState<Errors>({});
  const [touched, setTouched]   = useState<Partial<Record<keyof Fields, boolean>>>({});
  const [sent, setSent]         = useState(false);
  const [loading, setLoading]   = useState(false);
  const [serverError, setServerError] = useState("");

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    const updated = { ...form, [e.target.name]: e.target.value };
    setForm(updated);
    if (touched[e.target.name as keyof Fields]) setErrors(validate(updated));
  };

  const handleBlur = (
    e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    const name = e.target.name as keyof Fields;
    setTouched((t) => ({ ...t, [name]: true }));
    setErrors(validate(form));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setServerError("");

    const allTouched = Object.fromEntries(
      Object.keys(form).map((k) => [k, true])
    ) as Record<keyof Fields, boolean>;
    setTouched(allTouched);

    const errs = validate(form);
    setErrors(errs);
    if (Object.keys(errs).length > 0) return;

    setLoading(true);
    try {
      const res = await postForm("save_message.php", { ...form });
      if (res.success) {
        setSent(true);
      } else {
        setServerError(res.message ?? "Something went wrong. Please try again.");
      }
    } catch {
      setServerError("Could not reach the server. Please check your connection.");
    } finally {
      setLoading(false);
    }
  };

  const inputClass = (field: keyof Fields) =>
    `contact-input${touched[field] && errors[field] ? " contact-input-error" : ""}`;

  if (sent) {
    return (
      <div className="text-center py-12">
        <div className="w-16 h-16 bg-black flex items-center justify-center mx-auto mb-5 rounded">
          <CheckCircle className="w-8 h-8 text-white" />
        </div>
        <h3 className="font-black text-black mb-2" style={{ fontSize: "1.5rem", textTransform: "uppercase" }}>
          Message Sent!
        </h3>
        <p className="text-[#888] text-sm mb-6">We will get back to you as soon as possible.</p>
        <button
          onClick={() => { setSent(false); setForm(blank); setErrors({}); setTouched({}); }}
          className="btn-black"
        >
          Send Another
        </button>
      </div>
    );
  }

  return (
    <>
      <h2 className="font-black text-black mb-1" style={{ fontSize: "1.3rem", textTransform: "uppercase", letterSpacing: "-0.01em" }}>
        Send Us a Message
      </h2>
      <p className="text-[#888] text-sm mb-6">Have a question or feedback? We are all ears.</p>

      <form onSubmit={handleSubmit} noValidate className="space-y-4">

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="contact-label">Your Name</label>
            <input
              name="name" value={form.name}
              onChange={handleChange} onBlur={handleBlur}
              placeholder="Full Name"
              className={inputClass("name")}
            />
            <FieldError msg={touched.name ? errors.name : undefined} />
          </div>
          <div>
            <label className="contact-label">
              Phone <span className="text-[#bbb] normal-case font-normal">(optional)</span>
            </label>
            <input
              name="phone" value={form.phone}
              onChange={handleChange} onBlur={handleBlur}
              placeholder="0310 0000000"
              className={inputClass("phone")}
            />
            <FieldError msg={touched.phone ? errors.phone : undefined} />
          </div>
        </div>

        <div>
          <label className="contact-label">
            Email <span className="text-[#bbb] normal-case font-normal">(optional)</span>
          </label>
          <input
            name="email" type="email" value={form.email}
            onChange={handleChange} onBlur={handleBlur}
            placeholder="you@example.com"
            className={inputClass("email")}
          />
          <FieldError msg={touched.email ? errors.email : undefined} />
        </div>

        <div>
          <label className="contact-label">Subject</label>
          <select
            name="subject" value={form.subject}
            onChange={handleChange} onBlur={handleBlur}
            className={inputClass("subject")}
          >
            <option value="" disabled>Select a subject…</option>
            {subjects.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
          <FieldError msg={touched.subject ? errors.subject : undefined} />
        </div>

        <div>
          <label className="contact-label">Message</label>
          <textarea
            name="message" value={form.message}
            onChange={handleChange} onBlur={handleBlur}
            rows={4} placeholder="Your message…"
            className={`${inputClass("message")} resize-none`}
          />
          <FieldError msg={touched.message ? errors.message : undefined} />
        </div>

        {serverError && (
          <p className="text-[#e63946] text-xs font-medium bg-red-50 border border-red-100 rounded px-3 py-2">
            {serverError}
          </p>
        )}

        {!serverError && Object.keys(errors).length > 0 && Object.keys(touched).length > 0 && (
          <p className="text-[#e63946] text-xs font-medium">
            Please fix the errors above before sending.
          </p>
        )}

        <button
          type="submit" disabled={loading}
          className="btn-black w-full justify-center py-3 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {loading
            ? <><Loader2 className="w-4 h-4 animate-spin" /> Sending…</>
            : <><Send className="w-4 h-4" /> Send Message</>
          }
        </button>
      </form>
    </>
  );
}
