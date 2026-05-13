import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  KpisApiService,
  VtaDiaDiaResponse,
  VtaDiaDiaDia,
  VtaDiaDiaTotalesRow,
} from '../../services/kpis-api.service';

@Component({
  selector: 'app-kpi-vta-dia-dia',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './kpi-vta-dia-dia.component.html',
})
export class KpiVtaDiaDiaComponent implements OnInit {
  private readonly api = inject(KpisApiService);

  protected fecha = signal<string>(this.defaultFecha());
  protected loading = signal(false);
  protected error = signal('');
  protected data = signal<VtaDiaDiaResponse | null>(null);

  ngOnInit(): void {
    this.cargar();
  }

  protected onFechaChange(value: string): void {
    this.fecha.set(value);
    this.cargar();
  }

  protected cargar(): void {
    this.loading.set(true);
    this.error.set('');

    this.api.vtaDiaDia(this.fecha()).subscribe({
      next: (res) => {
        this.data.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err?.error?.message ?? 'Error al cargar el reporte.');
        this.loading.set(false);
      },
    });
  }

  // ── Formatters ──────────────────────────────────────────────────────────

  protected fmt(value: number | null | undefined): string {
    if (value == null) return '-';
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
      minimumFractionDigits: 2,
    }).format(value);
  }

  protected fmtPct(value: number | null | undefined): string {
    if (value == null) return '-';
    return (value > 0 ? '+' : '') + value.toFixed(1) + '%';
  }

  protected pctClass(value: number | null | undefined): string {
    if (value == null) return 'text-slate-400';
    return value >= 0 ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold';
  }

  protected varClass(value: number | null | undefined): string {
    if (value == null) return 'text-slate-400';
    return value >= 0 ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold';
  }

  // ── Helpers ─────────────────────────────────────────────────────────────

  private defaultFecha(): string {
    const today = new Date();
    // Use Monday of current week as default start
    const day = today.getDay(); // 0=Sun..6=Sat
    const diff = day === 0 ? -6 : 1 - day;
    const monday = new Date(today);
    monday.setDate(today.getDate() + diff);
    return monday.toISOString().slice(0, 10);
  }
}
