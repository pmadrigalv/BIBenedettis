import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export type TicketRow = {
  id: number;
  titulo: string;
  fecha: string | null;
  descripcion: string | null;
  estado: 'abierto' | 'en_proceso' | 'resuelto' | 'cerrado';
  prioridad: 'baja' | 'media' | 'alta' | 'urgente';
  usuario_id: number | null;
  usuario_nombre: string | null;
  tecnico_id: number | null;
  tecnico_nombre: string | null;
  unidad_id: number | null;
  unidad_nombre: string | null;
  imagenes: string[];
  imagenes_urls: string[];
  archivo_adjunto: string | null;
  archivo_url: string | null;
  created_at: string;
  updated_at?: string;
};

export type TicketListResponse = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  data: TicketRow[];
};

export type TicketSortBy = 'id' | 'titulo' | 'estado' | 'prioridad' | 'fecha' | 'created_at';
export type TicketSortDir = 'asc' | 'desc';

export type CrearTicketPayload = {
  titulo: string;
  fecha: string;
  descripcion: string | null;
  estado: string;
  prioridad: string;
  usuario_id: number;
  unidad_id: number | null;
  imagenes: File[];
  archivo: File | null;
};

@Injectable({ providedIn: 'root' })
export class TicketsApiService {
  private readonly http = inject(HttpClient);

  list(q: string, page: number, perPage: number, sortBy: TicketSortBy, sortDir: TicketSortDir) {
    return this.http.get<TicketListResponse>('/api/tickets', {
      params: { q, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir },
    });
  }

  create(payload: CrearTicketPayload) {
    const formData = new FormData();
    formData.append('titulo', payload.titulo);
    formData.append('fecha', payload.fecha);
    formData.append('descripcion', payload.descripcion ?? '');
    formData.append('estado', payload.estado);
    formData.append('prioridad', payload.prioridad);
    formData.append('usuario_id', String(payload.usuario_id));
    if (payload.unidad_id !== null) {
      formData.append('unidad_id', String(payload.unidad_id));
    }

    payload.imagenes.forEach((imagen) => {
      formData.append('imagenes[]', imagen);
    });

    if (payload.archivo) {
      formData.append('archivo', payload.archivo);
    }

    return this.http.post('/api/tickets', formData);
  }

  assignTecnico(ticketId: number, tecnicoId: number) {
    return this.http.put<TicketRow>(`/api/tickets/${ticketId}/asignar-tecnico`, {
      tecnico_id: tecnicoId,
    });
  }
}
