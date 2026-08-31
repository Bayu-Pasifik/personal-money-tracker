export function formatRupiah(amount: number): string {
  return 'Rp' + Math.abs(amount).toLocaleString('id-ID')
}

export function formatDateShort(dateString: string): string {
  return new Date(dateString).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}
