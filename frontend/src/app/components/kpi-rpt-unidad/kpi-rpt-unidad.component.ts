import { CommonModule } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { KpiComparisonChartComponent, KpiComparisonPoint } from '../shared/kpi-comparison-chart.component';
import {
  KpisApiService,
  RptUnidadCategoria,
  RptUnidadFila,
  RptUnidadPromocion,
  RptUnidadResponse,
  UnidadCatalogo,
} from '../../services/kpis-api.service';

@Component({
  selector: 'app-kpi-rpt-unidad',
  standalone: true,
  imports: [CommonModule, FormsModule, KpiComparisonChartComponent],
  templateUrl: './kpi-rpt-unidad.component.html',
})
export class KpiRptUnidadComponent implements OnInit {
  private readonly kpisApi = inject(KpisApiService);

  protected unidades = signal<UnidadCatalogo[]>([]);
  protected idUnidad = signal<number>(0);
  protected fechaInicio = signal<string>(this.defaultInicio());
  protected fechaFin    = signal<string>(this.todayStr());
  protected loading     = signal(false);
  protected error       = signal('');
  protected data        = signal<RptUnidadResponse | null>(null);

  ngOnInit(): void {
    this.kpisApi.unidadesCatalogo().subscribe({
      next: (list) => {
        this.unidades.set(list);
        if (list.length > 0 && this.idUnidad() === 0) {
          this.idUnidad.set(list[0].id_unidad);
        }
      },
      error: () => {},
    });
  }

  protected onIdUnidadChange(value: string): void {
    this.idUnidad.set(Number(value));
  }

  protected onFechaInicioChange(value: string): void {
    this.fechaInicio.set(value);
  }

  protected onFechaFinChange(value: string): void {
    this.fechaFin.set(value);
  }

  protected onActualizar(): void {
    this.load();
  }

  private load(): void {
    const id = this.idUnidad();
    const fi = this.fechaInicio();
    const ff = this.fechaFin();
    if (!id || !fi || !ff) return;

    this.loading.set(true);
    this.error.set('');
    this.data.set(null);

    this.kpisApi.rptUnidad(id, fi, ff).subscribe({
      next: (res) => {
        this.data.set(res);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err?.error?.message ?? err?.error?.error ?? 'Error al cargar el reporte.');
        this.loading.set(false);
      },
    });
  }

  protected totalCategoria(cat: RptUnidadCategoria): number {
    return cat.filas.reduce((acc, f) => acc + f.fx_actual, 0);
  }

  protected pctClass(v: number | null): string {
    if (v === null) return '';
    return v >= 0 ? 'text-green-600' : 'text-red-600';
  }

  protected fmt(v: number): string {
    return new Intl.NumberFormat('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 1 }).format(v);
  }

  protected fmtPct(v: number | null): string {
    if (v === null) return '—';
    return (v >= 0 ? '+' : '') + v.toFixed(1) + '%';
  }

  protected chartCategorias(categorias: RptUnidadCategoria[]): KpiComparisonPoint[] {
    return categorias.map((c) => ({
      label: c.tipo,
      prev: c.total_prev,
      current: c.total_actual,
    }));
  }

  protected chartPromociones(promociones: RptUnidadPromocion[]): KpiComparisonPoint[] {
    return promociones.map((p) => ({
      label: p.nombre,
      prev: p.fx_prev,
      current: p.fx_actual,
    }));
  }

  protected chartCategoriaFilas(filas: RptUnidadFila[]): KpiComparisonPoint[] {
    return filas.map((f) => ({
      label: f.nombre,
      prev: f.fx_prev,
      current: f.fx_actual,
    }));
  }

  private todayStr(): string {
    return new Date().toISOString().slice(0, 10);
  }

  private defaultInicio(): string {
    const d = new Date();
    d.setDate(d.getDate() - 6);
    return d.toISOString().slice(0, 10);
  }
}
