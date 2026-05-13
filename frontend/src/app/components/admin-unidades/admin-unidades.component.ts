import { Component, inject, signal } from '@angular/core';
import {
  ActualizarUnidadPayload,
  EstadoOption,
  RegionOption,
  TipoUnidadOption,
  UnidadDetail,
  UnidadUsuarioRelacion,
  UnidadSortBy,
  UnidadSortDir,
  UnidadesApiService,
  UnidadRow,
  ZonaOption,
} from '../../services/unidades-api.service';
import { SistemaUsuarioOption, UsuariosApiService } from '../../services/usuarios-api.service';

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

type UnidadDetailForm = {
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

@Component({
  selector: 'app-admin-unidades',
  standalone: true,
  templateUrl: './admin-unidades.component.html',
})
export class AdminUnidadesComponent {
  private readonly unidadesApi = inject(UnidadesApiService);
  private readonly usuariosApi = inject(UsuariosApiService);

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
  protected readonly unidadDetailModalOpen = signal(false);
  protected readonly unidadDetailLoading = signal(false);
  protected readonly unidadDetailSaving = signal(false);
  protected readonly unidadDetailError = signal('');
  protected readonly unidadDetalleId = signal<number | null>(null);
  protected readonly unidadUsuarios = signal<UnidadUsuarioRelacion[]>([]);
  protected readonly usuariosCatalogo = signal<SistemaUsuarioOption[]>([]);
  protected readonly unidadUsuarioSeleccionado = signal('');
  protected readonly unidadRelacionLoading = signal(false);
  protected readonly unidadAltaTiendaLoading = signal<number | null>(null);
  protected readonly relacionConfirmModalOpen = signal(false);
  protected readonly relacionConfirmUsuarioId = signal<number | null>(null);
  protected readonly relacionConfirmUsuarioNombre = signal('');
  protected readonly relacionConfirmAltaTienda = signal(false);
  protected readonly relacionConfirmSaving = signal(false);
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
  protected readonly unidadDetailForm = signal<UnidadDetailForm>(this.emptyUnidadDetailForm());

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

  protected openIpUnidad(ipUnidad: string | null): void {
    const url = this.buildUnidadUrl(ipUnidad, false);
    if (!url) {
      this.unidadesError.set('La unidad no tiene una IP valida para abrir la tienda.');
      return;
    }

    window.open(url, '_blank', 'noopener');
  }

  protected openWebminUnidad(ipUnidad: string | null): void {
    const url = this.buildUnidadUrl(ipUnidad, true);
    if (!url) {
      this.unidadesError.set('La unidad no tiene una IP valida para abrir Webmin.');
      return;
    }

    window.open(url, '_blank', 'noopener');
  }

  protected openUnidadDetailModal(unidadId: number): void {
    this.unidadDetailModalOpen.set(true);
    this.unidadDetailLoading.set(true);
    this.unidadDetailError.set('');
    this.unidadDetalleId.set(unidadId);
    this.unidadUsuarioSeleccionado.set('');

    if (this.estados().length === 0) {
      this.loadEstados();
    }
    if (this.zonas().length === 0) {
      this.loadZonas();
    }
    if (this.regiones().length === 0) {
      this.loadRegiones();
    }
    if (this.tiposUnidad().length === 0) {
      this.loadTiposUnidad();
    }

    this.unidadesApi.detail(unidadId).subscribe({
      next: (unidad) => {
        this.unidadDetailForm.set(this.mapUnidadDetailToForm(unidad));
        this.unidadDetailLoading.set(false);
      },
      error: () => {
        this.unidadDetailLoading.set(false);
        this.unidadDetailError.set('No se pudo cargar el detalle de la unidad.');
      },
    });

    this.loadUnidadUsuarios(unidadId);
    this.loadUsuariosCatalogo();
  }

  protected closeUnidadDetailModal(): void {
    this.unidadDetailModalOpen.set(false);
    this.unidadDetailLoading.set(false);
    this.unidadDetailSaving.set(false);
    this.unidadDetailError.set('');
    this.unidadDetalleId.set(null);
    this.unidadUsuarios.set([]);
    this.unidadUsuarioSeleccionado.set('');
    this.closeRelacionConfirmModal();
  }

  protected closeUnidadModal(): void {
    this.unidadModalOpen.set(false);
  }

  protected onUnidadFormChange(field: keyof UnidadForm, event: Event): void {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    const value = target.value;
    this.unidadForm.update((current) => ({ ...current, [field]: value }));
  }

  protected onUnidadDetailFormChange(field: keyof UnidadDetailForm, event: Event): void {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    this.unidadDetailForm.update((current) => ({ ...current, [field]: target.value }));
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

  protected guardarCambiosUnidad(): void {
    const unidadId = this.unidadDetalleId();
    const form = this.unidadDetailForm();

    if (!unidadId) {
      this.unidadDetailError.set('No se encontro la unidad a actualizar.');
      return;
    }

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
      this.unidadDetailError.set('Completa todos los campos obligatorios antes de guardar.');
      return;
    }

    this.unidadDetailError.set('');
    this.unidadDetailSaving.set(true);

    const payload: ActualizarUnidadPayload = {
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
    };

    this.unidadesApi.update(unidadId, payload).subscribe({
      next: () => {
        this.unidadDetailSaving.set(false);
        this.loadUnidades(this.unidadPage());
      },
      error: () => {
        this.unidadDetailSaving.set(false);
        this.unidadDetailError.set('No se pudo actualizar la unidad. Verifica los datos e intenta nuevamente.');
      },
    });
  }

  protected onUnidadUsuarioSeleccionado(event: Event): void {
    const target = event.target as HTMLSelectElement;
    this.unidadUsuarioSeleccionado.set(target.value);
  }

  protected agregarUsuarioRelacion(): void {
    const unidadId = this.unidadDetalleId();
    const usuarioId = Number(this.unidadUsuarioSeleccionado());

    if (!unidadId || !usuarioId) {
      this.unidadDetailError.set('Selecciona un usuario para relacionarlo a la unidad.');
      return;
    }

    const usuario = this.usuariosCatalogo().find((item) => item.id_usuario === usuarioId);

    this.unidadDetailError.set('');
    this.relacionConfirmUsuarioId.set(usuarioId);
    this.relacionConfirmUsuarioNombre.set(usuario?.nombre || 'Usuario seleccionado');
    this.relacionConfirmAltaTienda.set(false);
    this.relacionConfirmModalOpen.set(true);
  }

  protected closeRelacionConfirmModal(): void {
    this.relacionConfirmModalOpen.set(false);
    this.relacionConfirmUsuarioId.set(null);
    this.relacionConfirmUsuarioNombre.set('');
    this.relacionConfirmAltaTienda.set(false);
    this.relacionConfirmSaving.set(false);
  }

  protected onRelacionConfirmAltaTiendaChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    this.relacionConfirmAltaTienda.set(target.checked);
  }

  protected confirmarAgregarUsuarioRelacion(): void {
    const unidadId = this.unidadDetalleId();
    const usuarioId = this.relacionConfirmUsuarioId();

    if (!unidadId || !usuarioId) {
      this.unidadDetailError.set('No se pudo confirmar la relacion usuario-unidad.');
      return;
    }

    this.unidadDetailError.set('');
    this.relacionConfirmSaving.set(true);
    this.unidadRelacionLoading.set(true);

    this.unidadesApi.addUsuario(unidadId, usuarioId).subscribe({
      next: () => {
        if (!this.relacionConfirmAltaTienda()) {
          this.finishRelacionUsuarioSuccess(unidadId, 'Relacion usuario-unidad creada correctamente.');
          return;
        }

        this.unidadesApi.altaUsuarioTienda(unidadId, usuarioId).subscribe({
          next: (response) => {
            this.finishRelacionUsuarioSuccess(unidadId, response.message);
          },
          error: (error) => {
            this.relacionConfirmSaving.set(false);
            this.unidadRelacionLoading.set(false);
            this.closeRelacionConfirmModal();
            this.unidadUsuarioSeleccionado.set('');
            this.loadUnidadUsuarios(unidadId, true);

            const message = error?.error?.message || 'Relacion creada, pero fallo el insert remoto en tienda.';
            this.unidadDetailError.set(message);
          },
        });
      },
      error: (error) => {
        this.relacionConfirmSaving.set(false);
        this.unidadRelacionLoading.set(false);
        const message = error?.error?.message || 'No se pudo agregar el usuario a la unidad.';
        this.unidadDetailError.set(message);
      },
    });
  }

  protected quitarUsuarioRelacion(usuarioId: number): void {
    const unidadId = this.unidadDetalleId();

    if (!unidadId) {
      this.unidadDetailError.set('No se encontro la unidad para quitar la relacion.');
      return;
    }

    this.unidadDetailError.set('');
    this.unidadRelacionLoading.set(true);

    this.unidadesApi.removeUsuario(unidadId, usuarioId).subscribe({
      next: () => {
        this.loadUnidadUsuarios(unidadId, true);
      },
      error: () => {
        this.unidadRelacionLoading.set(false);
        this.unidadDetailError.set('No se pudo quitar el usuario de la unidad.');
      },
    });
  }

  protected darAltaUsuarioEnTienda(usuarioId: number): void {
    const unidadId = this.unidadDetalleId();

    if (!unidadId) {
      this.unidadDetailError.set('No se encontro la unidad para dar de alta al usuario en tienda.');
      return;
    }

    this.unidadDetailError.set('');
    this.unidadAltaTiendaLoading.set(usuarioId);

    this.unidadesApi.altaUsuarioTienda(unidadId, usuarioId).subscribe({
      next: (response) => {
        this.unidadAltaTiendaLoading.set(null);
        this.unidadDetailError.set('');
        this.unidadesError.set(response.message);
      },
      error: (error) => {
        this.unidadAltaTiendaLoading.set(null);
        const message = error?.error?.message || 'No se pudo dar de alta el usuario en la tienda.';
        this.unidadDetailError.set(message);
      },
    });
  }

  protected usuariosDisponiblesParaRelacionar(): SistemaUsuarioOption[] {
    const relacionados = new Set(this.unidadUsuarios().map((usuario) => usuario.id_usuario));
    return this.usuariosCatalogo().filter((usuario) => !relacionados.has(usuario.id_usuario));
  }

  protected asString(value: number): string {
    return `${value}`;
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

  private emptyUnidadDetailForm(): UnidadDetailForm {
    return {
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
    };
  }

  private mapUnidadDetailToForm(unidad: UnidadDetail): UnidadDetailForm {
    return {
      nombre_unidad: unidad.nombre_unidad || '',
      id_estado: unidad.id_estado === null || unidad.id_estado === undefined ? '' : String(unidad.id_estado),
      ciudad: unidad.ciudad || '',
      id_zona: unidad.id_zona === null || unidad.id_zona === undefined ? '' : String(unidad.id_zona),
      id_region: unidad.id_region === null || unidad.id_region === undefined ? '' : String(unidad.id_region),
      fapertura_unidad: this.toDateInput(unidad.fapertura_unidad),
      telefono_unidad: unidad.telefono_unidad || '',
      id_tipounidad: unidad.id_tipounidad === null || unidad.id_tipounidad === undefined ? '' : String(unidad.id_tipounidad),
      status_unidad: String(unidad.status_unidad ?? 1),
      alcancepedido_unidad:
        unidad.alcancepedido_unidad === null || unidad.alcancepedido_unidad === undefined
          ? ''
          : String(unidad.alcancepedido_unidad),
      clave_unidad: unidad.clave_unidad || '',
      uactip_unidad: this.toDateTimeLocalInput(unidad.uactip_unidad),
      ip_unidad: unidad.ip_unidad || '',
    };
  }

  private loadUnidadUsuarios(unidadId: number, keepLoading = false): void {
    if (!keepLoading) {
      this.unidadRelacionLoading.set(true);
    }

    this.unidadesApi.usuarios(unidadId).subscribe({
      next: (response) => {
        this.unidadUsuarios.set(response);
        this.unidadRelacionLoading.set(false);
      },
      error: () => {
        this.unidadRelacionLoading.set(false);
        this.unidadDetailError.set('No se pudieron cargar los usuarios relacionados.');
      },
    });
  }

  private loadUsuariosCatalogo(): void {
    this.usuariosApi.todos().subscribe({
      next: (response) => {
        this.usuariosCatalogo.set(response);
      },
      error: () => {
        this.unidadDetailError.set('No se pudo cargar el catalogo de usuarios.');
      },
    });
  }

  private buildUnidadUrl(ipUnidad: string | null, webmin: boolean): string | null {
    const ip = (ipUnidad || '').trim();
    if (!ip) {
      return null;
    }

    const hasProtocol = ip.startsWith('http://') || ip.startsWith('https://');
    const base = hasProtocol ? ip : `http://${ip}`;

    if (!webmin) {
      return base;
    }

    try {
      const url = new URL(base);
      url.port = '10000';
      return url.toString();
    } catch {
      return null;
    }
  }

  private toDateInput(value: string | null): string {
    if (!value) {
      return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    return date.toISOString().slice(0, 10);
  }

  private toDateTimeLocalInput(value: string | null): string {
    if (!value) {
      return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return '';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
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

  private finishRelacionUsuarioSuccess(unidadId: number, message: string): void {
    this.relacionConfirmSaving.set(false);
    this.unidadRelacionLoading.set(false);
    this.closeRelacionConfirmModal();
    this.unidadUsuarioSeleccionado.set('');
    this.unidadDetailError.set('');
    this.unidadesError.set(message);
    this.loadUnidadUsuarios(unidadId, true);
  }
}
