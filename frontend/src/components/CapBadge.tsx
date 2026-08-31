/**
 * StyleGuide.md §5.2 — elemen tanda tangan produk. Muncul saat transaksi baru
 * masuk (live) dan di ringkasan bulanan yang sudah "ditutup". Dekoratif saja —
 * status "tersimpan" tetap ada sebagai teks untuk screen reader (§8).
 */
export function CapBadge({ label = 'TERCATAT' }: { label?: string }) {
  return (
    <span
      className="cap-badge inline-flex items-center justify-center rounded-full border-double border-4 border-stamp-gold px-3 py-0.5 font-body text-[0.6875rem] font-semibold uppercase tracking-widest text-stamp-gold"
      style={{ transform: 'rotate(-8deg)' }}
      aria-hidden="true"
    >
      {label}
    </span>
  )
}
