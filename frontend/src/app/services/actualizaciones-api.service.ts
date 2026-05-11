import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export type ActualizacionRow = {
  id: number;
  titulo: string;
  descripcion: string | null;
  version: string | null;
  fecha_publicacion: string | null;
  created_at: string;
};

export type ActualizacionListResponse = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  data: ActualizacionRow[];
};

export type ActualizacionSortBy = 'id' | 'titulo' | 'version' | 'fecha_publicacion' | 'created_at';
export type ActualizacionSortDir = 'asc' | 'desc';

export type CrearActualizacionPayload = {
  titulo: string;
  descripcion: string | null;
  version: string | null;
  fecha_publicacion: string | null;
};

@Injectable({ providedIn: 'root' })
export class ActualizacionesApiService {
  private readonly http = inject(HttpClient);

  list(q: string, page: number, perPage: number, sortBy: ActualizacionSortBy, sortDir: ActualizacionSortDir) {
    return this.http.get<ActualizacionListResponse>('/api/actualizaciones', {
      params: { q, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir },
    });
  }

  create(payload: CrearActualizacionPayload) {
    return this.http.post('/api/actualizaciones', payload);
  }
}
