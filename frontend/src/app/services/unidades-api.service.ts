import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export type EstadoOption = {
  id_estado: number;
  nombre_estado: string;
};

export type ZonaOption = {
  id_zona: number;
  nombre_zona: string;
};

export type RegionOption = {
  id_region: number;
  nombre_region: string;
};

export type TipoUnidadOption = {
  id_tipounidad: number;
  nombre_tipounidad: string;
};

export type UnidadRow = {
  id_unidad: number;
  nombre_unidad: string;
  estado: string | null;
  uactip_unidad: string | null;
  ip_unidad: string | null;
};

export type UnidadListResponse = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  data: UnidadRow[];
};

export type UnidadSortBy = 'id_unidad' | 'nombre_unidad' | 'estado' | 'uactip_unidad' | 'ip_unidad';
export type UnidadSortDir = 'asc' | 'desc';

export type CrearUnidadPayload = {
  nombre_unidad: string;
  id_estado: number;
  ciudad: string;
  id_zona: number;
  id_region: number;
  fapertura_unidad: string;
  telefono_unidad: string;
  id_tipounidad: number;
  status_unidad: number;
  alcancepedido_unidad: number;
  clave_unidad: string;
  uactip_unidad: string | null;
  ip_unidad: string | null;
};

@Injectable({ providedIn: 'root' })
export class UnidadesApiService {
  private readonly http = inject(HttpClient);

  list(q: string, page: number, perPage: number, sortBy: UnidadSortBy, sortDir: UnidadSortDir) {
    return this.http.get<UnidadListResponse>('/api/unidades', {
      params: {
        q,
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      },
    });
  }

  estados() {
    return this.http.get<EstadoOption[]>('/api/catalogos/estados');
  }

  zonas() {
    return this.http.get<ZonaOption[]>('/api/catalogos/zonas');
  }

  regiones() {
    return this.http.get<RegionOption[]>('/api/catalogos/regiones');
  }

  tiposUnidad() {
    return this.http.get<TipoUnidadOption[]>('/api/catalogos/tipos-unidad');
  }

  create(payload: CrearUnidadPayload) {
    return this.http.post('/api/unidades', payload);
  }
}
