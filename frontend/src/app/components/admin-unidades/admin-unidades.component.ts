import { Component, inject, signal } from '@angular/core';
import {
  EstadoOption,
  RegionOption,
  TipoUnidadOption,
  UnidadSortBy,
  UnidadSortDir,
  UnidadesApiService,
  UnidadRow,
  ZonaOption,
} from '../../services/unidades-api.service';

type UnidadForm = {
  nombre_unidad: string;
  id_estado: string;
  ciudad: string;
  id_zona: string;
  id_region: string;
  fapertura_unidad: string;
  telefono_unidad: string;
  id_tipounidad: string;
  status_unidad: string;
  alcancepedido_unidad: string;
  clave_unidad: string;
  uactip_unidad: string;
  ip_unidad: string;
};

type UnidadGroup = {
  estado: string;
  unidades: UnidadRow[];
};

@Component({
  selector: 'app-admin-unidades',
  standalone: true,
  templateUrl: './admin-unidades.component.html',
})
export class AdminUnidadesComponent {
  private readonly unidadesApi = inject(UnidadesApiService);

  protected readonly unidades = signal<UnidadRow[]>([]);
  protected readonly unidadesLoading = signal(false);
  protected readonly unidadesError = signal('');
  protected readonly unidadSearch = signal('');
  protected readonly unidadPage = signal(1);
  protected readonly unidadLastPage = signal(1);
  protected readonly unidadTotal = signal(0);
  protected readonly unidadPerPage = signal(10);
  protected readonly unidadSortBy = signal<UnidadSortBy>('estado');
  protected readonly unidadSortDir = signal<UnidadSortDir>('asc');
  protected readonly unidadModalOpen = signal(false);
  protected readonly unidadSaving = signal(false);
  protected readonly estados = signal<EstadoOption[]>([]);
  protected readonly zonas = signal<ZonaOption[]>([]);
  protected readonly regiones = signal<RegionOption[]>([]);
  protected readonly tiposUnidad = signal<TipoUnidadOption[]>([]);
  protected readonly unidadForm = signal<UnidadForm>({
    nombre_unidad: '',
    id_estado: '',
    ciudad: '',
    id_zona: '',
    id_region: '',
    fapertura_unidad: '',
    telefono_unidad: '',
    id_tipounidad: '',
    status_unidad: '1',
    alcancepedido_unidad: '',
    clave_unidad: '',
    uactip_unidad: '',
    ip_unidad: '',
  });

  constructor() {
    this.loadEstados();
    this.loadZonas();
    this.loadRegiones();
    this.loadTiposUnidad();
    this.loadUnidades(1);
  }

  protected onUnidadSearchInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    this.unidadSearch.set(target.value);
  }

  protected searchUnidades(): void {
    this.loadUnidades(1);
  }

  protected goToUnidadPage(page: number): void {
    if (page < 1 || page > this.unidadLastPage() || page === this.unidadPage()) {
      return;
    }

    this.loadUnidades(page);
  }

  protected sortUnidades(field: UnidadSortBy): void {
    if (this.unidadSortBy() === field) {
      this.unidadSortDir.set(this.unidadSortDir() === 'asc' ? 'desc' : 'asc');
    } else {
      this.unidadSortBy.set(field);
      this.unidadSortDir.set(field === 'id_unidad' ? 'desc' : 'asc');
    }

    this.loadUnidades(1);
  }

  protected sortLabel(field: UnidadSortBy): string {
    if (this.unidadSortBy() !== field) {
      return '';
    }

    return this.unidadSortDir() === 'asc' ? '(asc)' : '(desc)';
  }

  protected openUnidadModal(): void {
    this.unidadForm.set({
      nombre_unidad: '',
      id_estado: '',
      ciudad: '',
      id_zona: '',
      id_region: '',
      fapertura_unidad: '',
      telefono_unidad: '',
      id_tipounidad: '',
      status_unidad: '1',
      alcancepedido_unidad: '',
      clave_unidad: '',
      uactip_unidad: '',
      ip_unidad: '',
    });
    this.unidadModalOpen.set(true);
  }

  protected closeUnidadModal(): void {
    this.unidadModalOpen.set(false);
  }

  protected onUnidadFormChange(field: keyof UnidadForm, event: Event): void {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    const value = target.value;
    this.unidadForm.update((current) => ({ ...current, [field]: value }));
  }

  protected registrarUnidad(): void {
    const form = this.unidadForm();

    if (
      !form.nombre_unidad.trim() ||
      !form.id_estado ||
      !form.ciudad.trim() ||
      !form.id_zona ||
      !form.id_region ||
      !form.fapertura_unidad ||
      !form.telefono_unidad.trim() ||
      !form.id_tipounidad ||
      !form.status_unidad ||
      !form.alcancepedido_unidad.trim() ||
      !form.clave_unidad.trim()
    ) {
      this.unidadesError.set('Completa todos los campos obligatorios de la unidad.');
      return;
    }

    this.unidadesError.set('');
    this.unidadSaving.set(true);

    this.unidadesApi
      .create({
        nombre_unidad: form.nombre_unidad.trim(),
        id_estado: Number(form.id_estado),
        ciudad: form.ciudad.trim(),
        id_zona: Number(form.id_zona),
        id_region: Number(form.id_region),
        fapertura_unidad: form.fapertura_unidad,
        telefono_unidad: form.telefono_unidad.trim(),
        id_tipounidad: Number(form.id_tipounidad),
        status_unidad: Number(form.status_unidad),
        alcancepedido_unidad: Number(form.alcancepedido_unidad),
        clave_unidad: form.clave_unidad.trim(),
        uactip_unidad: form.uactip_unidad || null,
        ip_unidad: form.ip_unidad || null,
      })
      .subscribe({
        next: () => {
          this.unidadSaving.set(false);
          this.closeUnidadModal();
          this.loadUnidades(1);
        },
        error: () => {
          this.unidadSaving.set(false);
          this.unidadesError.set('No se pudo registrar la unidad. Verifica los datos e intenta nuevamente.');
        },
      });
  }

  protected formatDateTime(value: string | null): string {
    if (!value) {
      return '-';
    }

    return new Date(value).toLocaleString('es-MX');
  }

  protected unidadPages(): number[] {
    const pages: number[] = [];
    const lastPage = this.unidadLastPage();

    for (let i = 1; i <= lastPage; i += 1) {
      pages.push(i);
    }

    return pages;
  }

  protected groupedUnidades(): UnidadGroup[] {
    const byEstado = new Map<string, UnidadRow[]>();

    for (const unidad of this.unidades()) {
      const estado = unidad.estado?.trim() || 'Sin estado';
      const items = byEstado.get(estado);

      if (items) {
        items.push(unidad);
      } else {
        byEstado.set(estado, [unidad]);
      }
    }

    return Array.from(byEstado.entries())
      .sort(([estadoA], [estadoB]) => estadoA.localeCompare(estadoB, 'es', { sensitivity: 'base' }))
      .map(([estado, unidades]) => ({
        estado,
        unidades: [...unidades].sort((a, b) => a.id_unidad - b.id_unidad),
      }));
  }

  private loadEstados(): void {
    this.unidadesApi.estados().subscribe({
      next: (response) => {
        this.estados.set(response);
      },
      error: () => {
        this.unidadesError.set('No se pudo cargar el catalogo de estados.');
      },
    });
  }

  private loadZonas(): void {
    this.unidadesApi.zonas().subscribe({
      next: (response) => {
        this.zonas.set(response);
      },
      error: () => {
        this.unidadesError.set('No se pudo cargar el catalogo de zonas.');
      },
    });
  }

  private loadRegiones(): void {
    this.unidadesApi.regiones().subscribe({
      next: (response) => {
        this.regiones.set(response);
      },
      error: () => {
        this.unidadesError.set('No se pudo cargar el catalogo de regiones.');
      },
    });
  }

  private loadTiposUnidad(): void {
    this.unidadesApi.tiposUnidad().subscribe({
      next: (response) => {
        this.tiposUnidad.set(response);
      },
      error: () => {
        this.unidadesError.set('No se pudo cargar el catalogo de tipos de unidad.');
      },
    });
  }

  private loadUnidades(page: number): void {
    this.unidadesLoading.set(true);
    this.unidadesError.set('');

    this.unidadesApi
      .list(this.unidadSearch(), page, this.unidadPerPage(), this.unidadSortBy(), this.unidadSortDir())
      .subscribe({
      next: (response) => {
        this.unidades.set(response.data);
        this.unidadPage.set(response.current_page);
        this.unidadLastPage.set(response.last_page);
        this.unidadTotal.set(response.total);
        this.unidadPerPage.set(response.per_page);
        this.unidadesLoading.set(false);
      },
      error: () => {
        this.unidadesLoading.set(false);
        this.unidadesError.set('No se pudo cargar la lista de unidades.');
      },
      });
  }
}
