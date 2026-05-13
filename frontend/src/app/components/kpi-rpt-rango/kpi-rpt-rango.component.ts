import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { KpiComparisonChartComponent, KpiComparisonPoint } from '../shared/kpi-comparison-chart.component';
import {
  KpisApiService,
  RptDiaAcumuladoDia,
  RptDiaAcumuladoSupervisor,
  RptDiaSupervisor,
  RptDiaUnidad,
  RptRangoResponse,
} from '../../services/kpis-api.service';

@Component({
  selector: 'app-kpi-rpt-rango',
  standalone: true,
  imports: [CommonModule, FormsModule, KpiComparisonChartComponent],
  templateUrl: './kpi-rpt-rango.component.html',
})
export class KpiRptRangoComponent {
  private readonly kpisApi = inject(KpisApiService);

  protected fechaInicio = signal<string>(this.defaultInicio());
  protected fechaFin    = signal<string>(this.todayStr());
  protected loading     = signal(false);
  protected error       = signal('');
  protected data        = signal<RptRangoResponse | null>(null);

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
    const fi = this.fechaInicio();
    const ff = this.fechaFin();
    if (!fi || !ff) return;

    this.loading.set(true);
    this.error.set('');
    this.data.set(null);

    this.kpisApi.rptRango(fi, ff).subscribe({
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

  /** Totals for a supervisor: sum of non-nueva units. */
  protected totalSupervisor(sup: RptDiaSupervisor): {
    fx_ac: number; fx_ap: number; txn_ac: number; txn_ap: number;
    var: number; pct_ap: number | null; presupuesto: number; variacion_pto: number; pct_aa: number | null;
  } {
    let fx_ac = 0, fx_ap = 0, txn_ac = 0, txn_ap = 0, presupuesto = 0;
    for (const u of sup.unidades) {
      if (u.fx_ac !== null) fx_ac += u.fx_ac;
      fx_ap      += u.fx_ap;
      if (u.txn_ac !== null) txn_ac += u.txn_ac;
      txn_ap     += u.txn_ap;
      presupuesto += u.presupuesto;
    }
    const varVal      = fx_ap - fx_ac;
    const variacion_pto = presupuesto > 0 ? fx_ap - presupuesto : 0;
    return {
      fx_ac, fx_ap, txn_ac, txn_ap,
      var: varVal,
      pct_ap: fx_ac > 0 ? Math.round((varVal / fx_ac) * 1000) / 10 : null,
      presupuesto,
      variacion_pto,
      pct_aa: presupuesto > 0 ? Math.round((variacion_pto / presupuesto) * 1000) / 10 : null,
    };
  }

  /** Totals for the acumulado-semana table. */
  protected totalesAcumulado(filas: RptDiaAcumuladoDia[]): {
    fxAcIguales: number; fxApIguales: number; fxApNuevas: number; fxApTotal: number;
    pto: number; var: number; pctAp: number | null; varPto: number; pctAa: number | null;
  } {
    let fxAcIguales = 0, fxApIguales = 0, fxApNuevas = 0, pto = 0, fxApConPto = 0;
    for (const f of filas) {
      fxAcIguales += f.fx_ac;
      fxApIguales += f.fx_ap;
      fxApNuevas  += f.fx_ap_nuevas;
      pto         += f.pto;
      if (f.pto > 0) fxApConPto += f.fx_ap;
    }
    const v      = fxApIguales - fxAcIguales;
    const varPto = pto > 0 ? fxApConPto - pto : 0;
    return {
      fxAcIguales, fxApIguales, fxApNuevas,
      fxApTotal: fxApIguales + fxApNuevas,
      pto, var: v,
      pctAp : fxAcIguales > 0 ? Math.round((v / fxAcIguales) * 1000) / 10 : null,
      varPto,
      pctAa : pto > 0 ? Math.round((varPto / pto) * 1000) / 10 : null,
    };
  }

  protected pctClass(v: number | null): string {
    if (v === null) return '';
    return v >= 0 ? 'text-green-600' : 'text-red-600';
  }

  protected fmt(v: number | null): string {
    if (v === null) return '—';
    return new Intl.NumberFormat('es-MX', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v);
  }

  protected fmtPct(v: number | null): string {
    if (v === null) return '—';
    return (v >= 0 ? '+' : '') + v.toFixed(1) + '%';
  }

  protected fmtMx(v: number): string {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2 }).format(v);
  }

  protected chartSemanal(filas: RptDiaAcumuladoDia[]): KpiComparisonPoint[] {
    return filas.map((f) => ({
      label: f.dia,
      prev: f.fx_ac,
      current: f.fx_ap,
    }));
  }

  protected chartAcumuladoSupervisores(filas: RptDiaAcumuladoSupervisor[]): KpiComparisonPoint[] {
    return filas.map((f) => ({
      label: f.supervisor,
      prev: f.fx_ac,
      current: f.fx_ap,
    }));
  }

  protected chartSupervisorUnidades(unidades: RptDiaUnidad[]): KpiComparisonPoint[] {
    return unidades.map((u) => ({
      label: u.nombre_unidad,
      prev: u.fx_ac ?? 0,
      current: u.fx_ap,
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
