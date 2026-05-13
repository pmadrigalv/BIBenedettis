import { CommonModule } from '@angular/common';
import { Component, Input } from '@angular/core';

export interface KpiComparisonPoint {
  label: string;
  prev: number;
  current: number;
}

@Component({
  selector: 'app-kpi-comparison-chart',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
      <div class="px-4 py-2 bg-slate-800 text-white text-xs font-semibold uppercase tracking-wide">
        {{ title }}
      </div>

      @if (points.length === 0) {
        <div class="px-4 py-4 text-sm text-gray-400 italic">Sin datos para graficar.</div>
      } @else {
        <div class="p-4 space-y-3">
          @for (p of points; track p.label) {
            <div class="grid grid-cols-[minmax(120px,1fr)_minmax(260px,3fr)] gap-3 items-center">
              <div class="text-xs font-semibold text-gray-700 truncate" [title]="p.label">{{ p.label }}</div>

              <div class="space-y-1.5">
                <div class="flex items-center gap-2">
                  <span class="w-16 text-[10px] uppercase tracking-wide text-gray-500">Ant</span>
                  <div class="h-2.5 w-full bg-gray-100 rounded overflow-hidden">
                    <div class="h-full bg-gray-400" [style.width.%]="barPct(p.prev)"></div>
                  </div>
                  <span class="text-[11px] font-semibold text-gray-600 w-16 text-right">{{ format(p.prev) }}</span>
                </div>

                <div class="flex items-center gap-2">
                  <span class="w-16 text-[10px] uppercase tracking-wide text-gray-500">Actual</span>
                  <div class="h-2.5 w-full bg-blue-50 rounded overflow-hidden">
                    <div class="h-full bg-blue-600" [style.width.%]="barPct(p.current)"></div>
                  </div>
                  <span class="text-[11px] font-semibold text-blue-700 w-16 text-right">{{ format(p.current) }}</span>
                </div>
              </div>
            </div>
          }
        </div>
      }
    </div>
  `,
})
export class KpiComparisonChartComponent {
  @Input() title = 'Comparativo';
  @Input() points: KpiComparisonPoint[] = [];
  @Input() mode: 'currency' | 'number' = 'currency';

  protected barPct(value: number): number {
    const max = this.maxValue();
    if (max <= 0 || value <= 0) {
      return 0;
    }
    return Math.max(2, Math.min(100, (value / max) * 100));
  }

  protected format(value: number): string {
    if (this.mode === 'number') {
      return new Intl.NumberFormat('es-MX', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    }

    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value);
  }

  private maxValue(): number {
    let max = 0;
    for (const p of this.points) {
      max = Math.max(max, p.prev, p.current);
    }
    return max;
  }
}
