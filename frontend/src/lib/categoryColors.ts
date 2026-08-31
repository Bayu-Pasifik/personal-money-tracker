/**
 * StyleGuide.md §5.3 — palet kategori muted 6-warna, dipakai sebagai border kiri
 * tipis / dot kecil. Hex literal dipetakan langsung (bukan class Tailwind dinamis)
 * karena color_key datang dari API saat runtime, di luar jangkauan JIT scanner.
 */
export const CATEGORY_COLORS: Record<string, string> = {
  'category-makanan': '#7A8B6F',
  'category-transportasi': '#6F8B9A',
  'category-belanja': '#9A7F6F',
  'category-hiburan': '#8B6F8A',
  'category-tagihan': '#9A8A5F',
  'category-kesehatan': '#6F9A8B',
}

export function categoryColor(colorKey: string | null): string {
  return (colorKey && CATEGORY_COLORS[colorKey]) || '#5B6358'
}
