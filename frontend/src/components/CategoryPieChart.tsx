import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'
import { categoryColor } from '../lib/categoryColors'
import { formatRupiah } from '../lib/format'
import type { CategoryBreakdown } from '../types'
import { EmptyState } from './EmptyState'

/**
 * StyleGuide.md §5.5 — pie chart kategori pakai palet kategori §5.3, bukan
 * warna random dari library.
 */
export function CategoryPieChart({ data }: { data: CategoryBreakdown[] }) {
  const expenseData = data.filter((item) => item.type === 'expense')

  if (expenseData.length === 0) {
    return <EmptyState message="Belum ada pengeluaran bulan ini untuk ditampilkan di grafik." />
  }

  return (
    <ResponsiveContainer width="100%" height={260}>
      <PieChart>
        <Pie
          data={expenseData}
          dataKey="total"
          nameKey="category_name"
          innerRadius={60}
          outerRadius={100}
          paddingAngle={2}
        >
          {expenseData.map((entry) => (
            <Cell key={entry.category_id} fill={categoryColor(entry.color_key)} stroke="none" />
          ))}
        </Pie>
        <Tooltip formatter={(value) => formatRupiah(Number(value))} />
      </PieChart>
    </ResponsiveContainer>
  )
}
