import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

export type NativeKpiReportType = 'vta-orilla' | 'vta-pizzas' | 'vta-adicionales' | 'rpt-vtas' | 'rpt-malas-ordenes';

export interface UnidadCatalogo {
  id_unidad: number;
  nombre_unidad: string;
}

export interface VtaDiaDiaDia {
  nombre: string;
  fecha: string;
  fx_anterior: number;
  fx_actual: number;
  pct_ap: number | null;
  variacion: number;
  pto: number;
  pct_aa: number | null;
  variacion_pto: number;
}

export interface VtaDiaDiaTotalesRow {
  fx_anterior: number | null;
  fx_actual: number | null;
  pct_ap: number | null;
  variacion: number | null;
  pto: number | null;
  pct_aa: number | null;
  variacion_pto: number | null;
}

export interface VtaDiaDiaResponse {
  semana: string;
  year: number;
  prev_year: number;
  dias: VtaDiaDiaDia[];
  totales: {
    iguales: VtaDiaDiaTotalesRow;
    nuevas: VtaDiaDiaTotalesRow;
    total: VtaDiaDiaTotalesRow;
  };
}

export interface NativeKpiColumn {
  key: string;
  label: string;
  type: 'text' | 'currency' | 'number' | 'percent';
}

export interface NativeKpiResponse {
  report: NativeKpiReportType;
  title: string;
  period: {
    inicio: string;
    fin: string;
    dias: number;
  };
  columns: NativeKpiColumn[];
  rows: Record<string, string | number | null>[];
  totals: Record<string, string | number | null>;
}

@Injectable({ providedIn: 'root' })
export class KpisApiService {
  private readonly http = inject(HttpClient);

  vtaDiaDia(fecha: string): Observable<VtaDiaDiaResponse> {
    const params = new HttpParams().set('fecha', fecha);
    return this.http.get<VtaDiaDiaResponse>('/api/kpis/vta-dia-dia', { params });
  }

  unidadesCatalogo(): Observable<UnidadCatalogo[]> {
    return this.http.get<UnidadCatalogo[]>('/api/catalogos/unidades');
  }

  nativeReport(reportType: NativeKpiReportType, fechaInicio: string, fechaFin: string, idUnidad?: number): Observable<NativeKpiResponse> {
    const pathMap: Record<NativeKpiReportType, string> = {
      'vta-orilla': '/api/kpis/native/vta-orilla',
      'vta-pizzas': '/api/kpis/native/vta-pizzas',
      'vta-adicionales': '/api/kpis/native/vta-adicionales',
      'rpt-vtas': '/api/kpis/native/rpt-vtas',
      'rpt-malas-ordenes': '/api/kpis/native/rpt-malas-ordenes',
    };

    let params = new HttpParams()
      .set('fecha_inicio', fechaInicio)
      .set('fecha_fin', fechaFin);

    if (idUnidad !== undefined && idUnidad !== 0) {
      params = params.set('id_unidad', idUnidad.toString());
    }

    return this.http.get<NativeKpiResponse>(pathMap[reportType], { params });
  }

  rptDia(fecha: string): Observable<RptDiaResponse> {
    const params = new HttpParams().set('fecha', fecha);
    return this.http.get<RptDiaResponse>('/api/kpis/rpt-dia', { params });
  }

  rptRango(fechaInicio: string, fechaFin: string): Observable<RptRangoResponse> {
    const params = new HttpParams()
      .set('fecha_inicio', fechaInicio)
      .set('fecha_fin', fechaFin);
    return this.http.get<RptRangoResponse>('/api/kpis/rpt-rango', { params });
  }

  rptUnidad(idUnidad: number, fechaInicio: string, fechaFin: string): Observable<RptUnidadResponse> {
    const params = new HttpParams()
      .set('id_unidad', idUnidad.toString())
      .set('fecha_inicio', fechaInicio)
      .set('fecha_fin', fechaFin);
    return this.http.get<RptUnidadResponse>('/api/kpis/rpt-unidad', { params });
  }

  rptOrillas(idUnidad: number, fechaInicio: string, fechaFin: string): Observable<RptOrillasResponse> {
    const params = new HttpParams()
      .set('id_unidad', idUnidad.toString())
      .set('fecha_inicio', fechaInicio)
      .set('fecha_fin', fechaFin);
    return this.http.get<RptOrillasResponse>('/api/kpis/rpt-orillas', { params });
  }
}

// ── Interfaces para RPT DIA ────────────────────────────────────────────────
export interface RptDiaUnidad {
  id_unidad: number;
  nombre_unidad: string;
  es_nueva: boolean;
  fx_ac: number | null;
  fx_ap: number;
  txn_ac: number | null;
  txn_ap: number;
  var: number | null;
  pct_ap: number | null;
  presupuesto: number;
  variacion_pto: number;
  pct_aa: number | null;
}

export interface RptDiaSupervisor {
  id_supervisor: number;
  supervisor: string;
  unidades: RptDiaUnidad[];
}

export interface RptDiaAcumuladoDia {
  dia: string;
  fecha_actual: string;
  fecha_comparativa: string;
  fx_ac: number;
  fx_ap: number;
  fx_ap_nuevas: number;
  pto: number;
  var: number;
  pct_ap: number | null;
  variacion_pto: number;
  pct_aa: number | null;
  txn_ac: number;
  txn_ap: number;
  txn_ap_nuevas: number;
}

export interface RptDiaAcumuladoSupervisor {
  supervisor: string;
  fx_ac: number;
  fx_ap: number;
  var: number;
  pct_ap: number | null;
  presupuesto: number;
  variacion_pto: number;
  pct_aa: number | null;
}

export interface RptDiaResponse {
  fecha: string;
  fecha_label: string;
  fecha_comparativa: string;
  fecha_comparativa_label: string;
  year_actual: number;
  year_anterior: number;
  semana_titulo: string;
  supervisores: RptDiaSupervisor[];
  acumulado_semana: RptDiaAcumuladoDia[];
  acumulado_supervisores: RptDiaAcumuladoSupervisor[];
}

// ── Interfaces para RPT RANGO ──────────────────────────────────────────────
export interface RptRangoResponse {
  fecha_inicio: string;
  fecha_fin: string;
  fecha_inicio_label: string;
  fecha_fin_label: string;
  fecha_comp_inicio: string;
  fecha_comp_fin: string;
  fecha_comp_inicio_label: string;
  fecha_comp_fin_label: string;
  year_actual: number;
  year_anterior: number;
  titulo: string;
  supervisores: RptDiaSupervisor[];
  acumulado_semana: RptDiaAcumuladoDia[];
  acumulado_supervisores: RptDiaAcumuladoSupervisor[];
}

// ── Interfaces para RPT UNIDAD (Rep.Ventas) ───────────────────────────────
export interface RptUnidadFila {
  id_tamanno: number;
  nombre: string;
  fx_prev: number;
  fx_actual: number;
  pct_ap: number | null;
  variacion: number;
}

export interface RptUnidadCategoria {
  tipo: string;
  slug: string;
  filas: RptUnidadFila[];
  total_prev: number;
  total_actual: number;
  total_pct_ap: number | null;
  total_var: number;
}

export interface RptUnidadPromocion {
  id_esquemacobro: number;
  nombre: string;
  fx_prev: number;
  fx_actual: number;
  pct_ap: number | null;
  variacion: number;
}

export interface RptUnidadResponse {
  id_unidad: number;
  nombre_unidad: string;
  fecha_inicio: string;
  fecha_fin: string;
  fecha_comp_inicio: string;
  fecha_comp_fin: string;
  year_actual: number;
  year_anterior: number;
  categorias: RptUnidadCategoria[];
  promociones: RptUnidadPromocion[];
  total_promociones: {
    fx_prev: number;
    fx_actual: number;
    pct_ap: number | null;
    variacion: number;
  };
}

// ── Interfaces para RPT ORILLAS ───────────────────────────────────────────
export interface RptOrillasFila {
  id_tamanno: number;
  nombre: string;
  fx_prev: number;
  fx_actual: number;
  pct_ap: number | null;
  variacion: number;
}

export interface RptOrillasReceta {
  id_receta: number;
  nombre: string;
  slug: string;
  filas: RptOrillasFila[];
  total_prev: number;
  total_actual: number;
  total_pct_ap: number | null;
  total_var: number;
  sin_ventas: boolean;
}

export interface RptOrillasResponse {
  id_unidad: number;
  nombre_unidad: string;
  fecha_inicio: string;
  fecha_fin: string;
  fecha_comp_inicio: string;
  fecha_comp_fin: string;
  year_actual: number;
  year_anterior: number;
  orillas: RptOrillasReceta[];
}
