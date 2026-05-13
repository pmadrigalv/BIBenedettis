import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export type AutoridadOption = {
  id_autoridad: number;
  descripcion_autoridad: string;
};

export type UsuarioRow = {
  id_usuario: number;
  uid_usuario: string;
  nombres_usuario: string;
  apellidos_usuario: string;
  telefono_usuario: string | null;
  email_usuario: string;
  vigencia_usuario: boolean;
  autoridad: string | null;
};

export type UsuarioListResponse = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  data: UsuarioRow[];
};

export type UsuarioSortBy =
  | 'id_usuario'
  | 'uid_usuario'
  | 'nombres_usuario'
  | 'apellidos_usuario'
  | 'email_usuario'
  | 'autoridad';

export type UsuarioSortDir = 'asc' | 'desc';

export type SistemaUsuarioOption = {
  id_usuario: number;
  nombre: string;
  autoridad?: string | null;
};

export type UsuarioUnidadOption = {
  id_unidad: number;
  nombre_unidad: string;
};

export type CrearUsuarioPayload = {
  uid_usuario: string;
  pwd_usuario: string;
  nombres_usuario: string;
  apellidos_usuario: string;
  telefono_usuario: string | null;
  email_usuario: string;
  id_autoridad: number | null;
  vigencia_usuario: boolean | number | null;
};

export type ActualizarUsuarioPayload = {
  uid_usuario: string;
  pwd_usuario: string | null;
  nombres_usuario: string;
  apellidos_usuario: string;
  telefono_usuario: string | null;
  email_usuario: string;
  id_autoridad: number | null;
  vigencia_usuario: boolean | number | null;
};

export type UsuarioDetail = {
  id_usuario: number;
  uid_usuario: string;
  nombres_usuario: string;
  apellidos_usuario: string;
  telefono_usuario: string | null;
  email_usuario: string;
  id_autoridad: number | null;
  vigencia_usuario: boolean;
};

export type UnidadRelacionUsuario = {
  id_unidad: number;
  nombre_unidad: string;
  ip_unidad: string | null;
  status_unidad: number;
};

@Injectable({ providedIn: 'root' })
export class UsuariosApiService {
  private readonly http = inject(HttpClient);

  list(q: string, page: number, perPage: number, sortBy: UsuarioSortBy, sortDir: UsuarioSortDir) {
    return this.http.get<UsuarioListResponse>('/api/usuarios', {
      params: {
        q,
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      },
    });
  }

  autoridades() {
    return this.http.get<AutoridadOption[]>('/api/catalogos/autoridades');
  }

  sistemas() {
    return this.http.get<SistemaUsuarioOption[]>('/api/catalogos/usuarios-sistemas');
  }

  todos() {
    return this.http.get<SistemaUsuarioOption[]>('/api/catalogos/usuarios');
  }

  solicitantes() {
    return this.http.get<SistemaUsuarioOption[]>('/api/catalogos/solicitantes');
  }

  unidadesPorUsuario(usuarioId: number) {
    return this.http.get<UsuarioUnidadOption[]>(`/api/catalogos/usuarios/${usuarioId}/unidades`);
  }

  unidadesCatalogo() {
    return this.http.get<UnidadRelacionUsuario[]>('/api/catalogos/unidades');
  }

  create(payload: CrearUsuarioPayload) {
    return this.http.post('/api/usuarios', payload);
  }

  detail(usuarioId: number) {
    return this.http.get<UsuarioDetail>(`/api/usuarios/${usuarioId}`);
  }

  update(usuarioId: number, payload: ActualizarUsuarioPayload) {
    return this.http.put(`/api/usuarios/${usuarioId}`, payload);
  }

  unidades(usuarioId: number) {
    return this.http.get<UnidadRelacionUsuario[]>(`/api/usuarios/${usuarioId}/unidades`);
  }

  addUnidad(usuarioId: number, unidadId: number) {
    return this.http.post(`/api/usuarios/${usuarioId}/unidades`, { id_unidad: unidadId });
  }

  removeUnidad(usuarioId: number, unidadId: number) {
    return this.http.delete(`/api/usuarios/${usuarioId}/unidades/${unidadId}`);
  }
}