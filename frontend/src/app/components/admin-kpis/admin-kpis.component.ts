import { CommonModule } from '@angular/common';
import { Component, Input, OnChanges, OnInit, SimpleChanges, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { KpisApiService, NativeKpiColumn, NativeKpiResponse, NativeKpiReportType, UnidadCatalogo } from '../../services/kpis-api.service';

export type KpiReportType = NativeKpiReportType;

@Component({
  selector: 'app-admin-kpis',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './admin-kpis.component.html',
})
export class AdminKpisComponent implements OnChanges, OnInit {
  constructor(private readonly kpisApi: KpisApiService) {}

  @Input() reportType: KpiReportType = 'vta-orilla';

  protected readonly fechaInicio = signal(this.defaultStartDate());
  protected readonly fechaFin = signal(this.defaultEndDate());
  protected readonly loading = signal(false);
  protected readonly error = signal('');
  protected readonly reportData = signal<NativeKpiResponse | null>(null);
  protected readonly unidades = signal<UnidadCatalogo[]>([]);
  protected readonly selectedUnidad = signal<number>(0);

  ngOnInit(): void {
    this.kpisApi.unidadesCatalogo().subscribe({
      next: (list) => this.unidades.set(list),
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['reportType']) {
      this.reportData.set(null);
      this.error.set('');
    }
  }

  protected reportTitle(): string {
    const map: Record<KpiReportType, string> = {
      'vta-orilla': 'VTA ORILLA',
      'vta-pizzas': 'VTA PIZZAS',
      'vta-adicionales': 'VTA ADICIONALES',
      'rpt-vtas': 'RPT VTAS',
      'rpt-malas-ordenes': 'RPT MALAS ORDENES',
    };
    return map[this.reportType];
  }

  protected onReload(): void {
    this.loadReport();
  }

  protected onFechaInicioChange(value: string): void {
    this.fechaInicio.set(value);
  }

  protected onFechaFinChange(value: string): void {
    this.fechaFin.set(value);
  }

  protected onUnidadChange(value: string): void {
    this.selectedUnidad.set(Number(value));
  }

  protected formatValue(value: string | number | null | undefined, column: NativeKpiColumn): string {
    if (value === null || value === undefined || value === '') {
      return '-';
    }
    if (column.type === 'text') {
      return String(value);
    }
    const numericValue = Number(value);
    if (Number.isNaN(numericValue)) {
      return String(value);
    }
    if (column.type === 'currency') {
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
      }).format(numericValue);
    }
    if (column.type === 'percent') {
      return `${numericValue.toFixed(1)}%`;
    }
    return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(numericValue);
  }

  protected valueClass(column: NativeKpiColumn, value: string | number | null | undefined): string {
    if (column.type === 'text' || value === null || value === undefined || value === '') {
      return 'text-slate-700';
    }
    const numericValue = Number(value);
    if (Number.isNaN(numericValue)) {
      return 'text-slate-700';
    }
    if (column.type === 'percent' || column.key.includes('variacion') || column.key === 'fx_actual' || column.key === 'fx_prev') {
      if (numericValue > 0) return 'text-emerald-600 font-semibold';
      if (numericValue < 0) return 'text-red-600 font-semibold';
    }
    return 'text-slate-700';
  }

  private loadReport(): void {
    if (!this.fechaInicio() || !this.fechaFin()) {
      return;
    }
    this.loading.set(true);
    this.error.set('');
    this.kpisApi.nativeReport(this.reportType, this.fechaInicio(), this.fechaFin(), this.selectedUnidad()).subscribe({
      next: (response) => {
        this.reportData.set(response);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err?.error?.message ?? 'No fue posible cargar el reporte KPI.');
        this.loading.set(false);
      },
    });
  }

  private defaultEndDate(): string {
    return new Date().toISOString().slice(0, 10);
  }

  private defaultStartDate(): string {
    const today = new Date();
    const start = new Date(today);
    start.setDate(today.getDate() - 6);
    return start.toISOString().slice(0, 10);
  }
}
