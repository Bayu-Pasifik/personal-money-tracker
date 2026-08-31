/**
 * StyleGuide.md §5.4 — kalau AI menyertakan angka (mis. "saldo Rp2.100.000") di
 * dalam kalimat, angka itu tetap dirender IBM Plex Mono meski kalimatnya Inter.
 */
export function AdvisoryMessageText({ text }: { text: string }) {
  const parts = text.split(/(Rp\s?[\d.,]+)/g)

  return (
    <>
      {parts.map((part, index) =>
        /^Rp\s?[\d.,]+$/.test(part) ? (
          <span key={index} className="font-data tabular-nums">
            {part}
          </span>
        ) : (
          <span key={index}>{part}</span>
        ),
      )}
    </>
  )
}
