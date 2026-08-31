/**
 * StyleGuide.md §7 — empty state = ajakan bertindak, bukan sekadar "tidak ada data".
 */
export function EmptyState({ message }: { message: string }) {
  return (
    <div className="rounded-md border border-dashed border-border bg-paper-elevated px-6 py-10 text-center">
      <p className="font-body text-sm text-ink-muted">{message}</p>
    </div>
  )
}
