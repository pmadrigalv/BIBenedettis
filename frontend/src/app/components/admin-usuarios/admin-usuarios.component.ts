import { Component, inject, signal } from '@angular/core';
import {
  ActualizarUsuarioPayload,
  AutoridadOption,
  UnidadRelacionUsuario,
  UsuarioDetail,
  UsuarioRow,
  UsuarioSortBy,
  UsuarioSortDir,
  UsuariosApiService,
} from '../../services/usuarios-api.service';
import { UnidadesApiService } from '../../services/unidades-api.service';

type UsuarioForm = {
  uid_usuario: string;
  pwd_usuario: string;
  nombres_usuario: string;
  apellidos_usuario: string;
  telefono_usuario: string;
  email_usuario: string;
  id_autoridad: string;
  vigencia_usuario: string;
};

type UsuarioDetailForm = {
  uid_usuario: string;
  pwd_usuario: string;
  nombres_usuario: string;
  apellidos_usuario: string;
  telefono_usuario: string;
  email_usuario: string;
  id_autoridad: string;
  vigencia_usuario: string;
};

@Component({
  selector: 'app-admin-usuarios',
  standalone: true,
  templateUrl: './admin-usuarios.component.html',
})
export class AdminUsuariosComponent {
  private readonly usuariosApi = inject(UsuariosApiService);
  private readonly unidadesApi = inject(UnidadesApiService);

  protected readonly usuarios = signal<UsuarioRow[]>([]);
  protected readonly usuariosLoading = signal(false);
  protected readonly usuariosError = signal('');
  protected readonly usuarioSearch = signal('');
  protected readonly usuarioPage = signal(1);
  protected readonly usuarioLastPage = signal(1);
  protected readonly usuarioTotal = signal(0);
  protected readonly usuarioPerPage = signal(10);
  protected readonly usuarioSortBy = signal<UsuarioSortBy>('id_usuario');
  protected readonly usuarioSortDir = signal<UsuarioSortDir>('desc');
  protected readonly usuarioModalOpen = signal(false);
  protected readonly usuarioSaving = signal(false);
  protected readonly autoridades = signal<AutoridadOption[]>([]);
  protected readonly usuarioDetailModalOpen = signal(false);
  protected readonly usuarioDetailLoading = signal(false);
  protected readonly usuarioDetailSaving = signal(false);
  protected readonly usuarioDetailError = signal('');
  protected readonly usuarioDetalleId = signal<number | null>(null);
  protected readonly usuarioDetailForm = signal<UsuarioDetailForm>(this.emptyUsuarioDetailForm());
  protected readonly usuarioUnidades = signal<UnidadRelacionUsuario[]>([]);
  protected readonly unidadesCatalogo = signal<UnidadRelacionUsuario[]>([]);
  protected readonly usuarioUnidadSeleccionada = signal('');
  protected readonly usuarioRelacionLoading = signal(false);
  protected readonly usuarioAltaTiendaLoading = signal<number | null>(null);
  protected readonly usuarioRelacionConfirmModalOpen = signal(false);
  protected readonly usuarioRelacionConfirmUnidadId = signal<number | null>(null);
  protected readonly usuarioRelacionConfirmUnidadNombre = signal('');
  protected readonly usuarioRelacionConfirmAltaTienda = signal(false);
  protected readonly usuarioRelacionConfirmSaving = signal(false);
  protected readonly usuarioForm = signal<UsuarioForm>({
    uid_usuario: '',
    pwd_usuario: '',
    nombres_usuario: '',
    apellidos_usuario: '',
    telefono_usuario: '',
    email_usuario: '',
    id_autoridad: '',
    vigencia_usuario: '1',
  });

  constructor() {
    this.loadAutoridades();
    this.loadUsuarios(1);
  }

  protected onUsuarioSearchInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    this.usuarioSearch.set(target.value);
  }

  protected searchUsuarios(): void {
    this.loadUsuarios(1);
  }

  protected goToUsuarioPage(page: number): void {
    if (page < 1 || page > this.usuarioLastPage() || page === this.usuarioPage()) {
      return;
    }

    this.loadUsuarios(page);
  }

  protected sortUsuarios(field: UsuarioSortBy): void {
    if (this.usuarioSortBy() === field) {
      this.usuarioSortDir.set(this.usuarioSortDir() === 'asc' ? 'desc' : 'asc');
    } else {
      this.usuarioSortBy.set(field);
      this.usuarioSortDir.set(field === 'id_usuario' ? 'desc' : 'asc');
    }

    this.loadUsuarios(1);
  }

  protected sortLabel(field: UsuarioSortBy): string {
    if (this.usuarioSortBy() !== field) {
      return '';
    }

    return this.usuarioSortDir() === 'asc' ? '(asc)' : '(desc)';
  }

  protected openUsuarioModal(): void {
    this.usuarioForm.set({
      uid_usuario: '',
      pwd_usuario: '',
      nombres_usuario: '',
      apellidos_usuario: '',
      telefono_usuario: '',
      email_usuario: '',
      id_autoridad: '',
      vigencia_usuario: '1',
    });
    this.usuarioModalOpen.set(true);
  }

  protected closeUsuarioModal(): void {
    this.usuarioModalOpen.set(false);
  }

  protected openUsuarioDetailModal(usuarioId: number): void {
    this.usuarioDetailModalOpen.set(true);
    this.usuarioDetailLoading.set(true);
    this.usuarioDetailError.set('');
    this.usuarioDetalleId.set(usuarioId);
    this.usuarioUnidadSeleccionada.set('');

    this.usuariosApi.detail(usuarioId).subscribe({
      next: (usuario) => {
        this.usuarioDetailForm.set(this.mapUsuarioDetailToForm(usuario));
        this.usuarioDetailLoading.set(false);
      },
      error: () => {
        this.usuarioDetailLoading.set(false);
        this.usuarioDetailError.set('No se pudo cargar el detalle del usuario.');
      },
    });

    this.loadUsuarioUnidades(usuarioId);
    this.loadUnidadesCatalogo();
  }

  protected closeUsuarioDetailModal(): void {
    this.usuarioDetailModalOpen.set(false);
    this.usuarioDetailLoading.set(false);
    this.usuarioDetailSaving.set(false);
    this.usuarioDetailError.set('');
    this.usuarioDetalleId.set(null);
    this.usuarioUnidades.set([]);
    this.usuarioUnidadSeleccionada.set('');
    this.closeUsuarioRelacionConfirmModal();
  }

  protected onUsuarioFormChange(field: keyof UsuarioForm, event: Event): void {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    const value = target.value;
    this.usuarioForm.update((current) => ({ ...current, [field]: value }));
  }

  protected onUsuarioDetailFormChange(field: keyof UsuarioDetailForm, event: Event): void {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    this.usuarioDetailForm.update((current) => ({ ...current, [field]: target.value }));
  }

  protected registrarUsuario(): void {
    const form = this.usuarioForm();

    if (
      !form.uid_usuario.trim() ||
      !form.pwd_usuario.trim() ||
      !form.nombres_usuario.trim() ||
      !form.apellidos_usuario.trim() ||
      !form.email_usuario.trim()
    ) {
      this.usuariosError.set('Completa los campos obligatorios del usuario.');
      return;
    }

    this.usuariosError.set('');
    this.usuarioSaving.set(true);

    this.usuariosApi
      .create({
        uid_usuario: form.uid_usuario.trim(),
        pwd_usuario: form.pwd_usuario,
        nombres_usuario: form.nombres_usuario.trim(),
        apellidos_usuario: form.apellidos_usuario.trim(),
        telefono_usuario: form.telefono_usuario.trim() || null,
        email_usuario: form.email_usuario.trim(),
        id_autoridad: form.id_autoridad ? Number(form.id_autoridad) : null,
        vigencia_usuario: form.vigencia_usuario === '1',
      })
      .subscribe({
        next: () => {
          this.usuarioSaving.set(false);
          this.closeUsuarioModal();
          this.loadUsuarios(1);
        },
        error: () => {
          this.usuarioSaving.set(false);
          this.usuariosError.set('No se pudo registrar el usuario. Verifica los datos e intenta nuevamente.');
        },
      });
  }

  protected guardarCambiosUsuario(): void {
    const usuarioId = this.usuarioDetalleId();
    const form = this.usuarioDetailForm();

    if (!usuarioId) {
      this.usuarioDetailError.set('No se encontro el usuario a actualizar.');
      return;
    }

    if (
      !form.uid_usuario.trim() ||
      !form.nombres_usuario.trim() ||
      !form.apellidos_usuario.trim() ||
      !form.email_usuario.trim()
    ) {
      this.usuarioDetailError.set('Completa los campos obligatorios del usuario.');
      return;
    }

    this.usuarioDetailError.set('');
    this.usuarioDetailSaving.set(true);

    const payload: ActualizarUsuarioPayload = {
      uid_usuario: form.uid_usuario.trim(),
      pwd_usuario: form.pwd_usuario.trim() ? form.pwd_usuario : null,
      nombres_usuario: form.nombres_usuario.trim(),
      apellidos_usuario: form.apellidos_usuario.trim(),
      telefono_usuario: form.telefono_usuario.trim() || null,
      email_usuario: form.email_usuario.trim(),
      id_autoridad: form.id_autoridad ? Number(form.id_autoridad) : null,
      vigencia_usuario: form.vigencia_usuario === '1',
    };

    this.usuariosApi.update(usuarioId, payload).subscribe({
      next: () => {
        this.usuarioDetailSaving.set(false);
        this.loadUsuarios(this.usuarioPage());
      },
      error: () => {
        this.usuarioDetailSaving.set(false);
        this.usuarioDetailError.set('No se pudo actualizar el usuario. Verifica los datos e intenta nuevamente.');
      },
    });
  }

  protected onUsuarioUnidadSeleccionada(event: Event): void {
    const target = event.target as HTMLSelectElement;
    this.usuarioUnidadSeleccionada.set(target.value);
  }

  protected agregarUnidadRelacion(): void {
    const unidadId = Number(this.usuarioUnidadSeleccionada());

    if (!unidadId) {
      this.usuarioDetailError.set('Selecciona una unidad para relacionarla al usuario.');
      return;
    }

    const unidad = this.unidadesCatalogo().find((item) => item.id_unidad === unidadId);

    this.usuarioDetailError.set('');
    this.usuarioRelacionConfirmUnidadId.set(unidadId);
    this.usuarioRelacionConfirmUnidadNombre.set(unidad?.nombre_unidad || 'Unidad seleccionada');
    this.usuarioRelacionConfirmAltaTienda.set(false);
    this.usuarioRelacionConfirmModalOpen.set(true);
  }

  protected closeUsuarioRelacionConfirmModal(): void {
    this.usuarioRelacionConfirmModalOpen.set(false);
    this.usuarioRelacionConfirmUnidadId.set(null);
    this.usuarioRelacionConfirmUnidadNombre.set('');
    this.usuarioRelacionConfirmAltaTienda.set(false);
    this.usuarioRelacionConfirmSaving.set(false);
  }

  protected onUsuarioRelacionConfirmAltaTiendaChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    this.usuarioRelacionConfirmAltaTienda.set(target.checked);
  }

  protected confirmarAgregarUnidadRelacion(): void {
    const usuarioId = this.usuarioDetalleId();
    const unidadId = this.usuarioRelacionConfirmUnidadId();

    if (!usuarioId || !unidadId) {
      this.usuarioDetailError.set('No se pudo confirmar la relacion usuario-unidad.');
      return;
    }

    this.usuarioDetailError.set('');
    this.usuarioRelacionConfirmSaving.set(true);
    this.usuarioRelacionLoading.set(true);

    this.usuariosApi.addUnidad(usuarioId, unidadId).subscribe({
      next: () => {
        if (!this.usuarioRelacionConfirmAltaTienda()) {
          this.finishUnidadRelacionSuccess(usuarioId, 'Relacion usuario-unidad creada correctamente.');
          return;
        }

        this.unidadesApi.altaUsuarioTienda(unidadId, usuarioId).subscribe({
          next: (response) => {
            this.finishUnidadRelacionSuccess(usuarioId, response.message);
          },
          error: (error) => {
            this.usuarioRelacionConfirmSaving.set(false);
            this.usuarioRelacionLoading.set(false);
            this.closeUsuarioRelacionConfirmModal();
            this.usuarioUnidadSeleccionada.set('');
            this.loadUsuarioUnidades(usuarioId, true);

            const message = error?.error?.message || 'Relacion creada, pero fallo el insert remoto en tienda.';
            this.usuarioDetailError.set(message);
          },
        });
      },
      error: (error) => {
        this.usuarioRelacionConfirmSaving.set(false);
        this.usuarioRelacionLoading.set(false);
        const message = error?.error?.message || 'No se pudo agregar la unidad al usuario.';
        this.usuarioDetailError.set(message);
      },
    });
  }

  protected quitarUnidadRelacion(unidadId: number): void {
    const usuarioId = this.usuarioDetalleId();

    if (!usuarioId) {
      this.usuarioDetailError.set('No se encontro el usuario para quitar la relacion.');
      return;
    }

    this.usuarioDetailError.set('');
    this.usuarioRelacionLoading.set(true);

    this.usuariosApi.removeUnidad(usuarioId, unidadId).subscribe({
      next: () => {
        this.loadUsuarioUnidades(usuarioId, true);
      },
      error: () => {
        this.usuarioRelacionLoading.set(false);
        this.usuarioDetailError.set('No se pudo quitar la unidad de la relacion con el usuario.');
      },
    });
  }

  protected darAltaUsuarioEnUnidad(unidadId: number): void {
    const usuarioId = this.usuarioDetalleId();

    if (!usuarioId) {
      this.usuarioDetailError.set('No se encontro el usuario para dar de alta en la unidad.');
      return;
    }

    this.usuarioDetailError.set('');
    this.usuarioAltaTiendaLoading.set(unidadId);

    this.unidadesApi.altaUsuarioTienda(unidadId, usuarioId).subscribe({
      next: (response) => {
        this.usuarioAltaTiendaLoading.set(null);
        this.usuariosError.set(response.message);
      },
      error: (error) => {
        this.usuarioAltaTiendaLoading.set(null);
        const message = error?.error?.message || 'No se pudo dar de alta el usuario en la unidad seleccionada.';
        this.usuarioDetailError.set(message);
      },
    });
  }

  protected unidadesDisponiblesParaRelacionar(): UnidadRelacionUsuario[] {
    const relacionadas = new Set(this.usuarioUnidades().map((unidad) => unidad.id_unidad));
    return this.unidadesCatalogo().filter((unidad) => !relacionadas.has(unidad.id_unidad));
  }

  protected asString(value: number): string {
    return `${value}`;
  }

  protected usuarioPages(): number[] {
    const pages: number[] = [];
    const lastPage = this.usuarioLastPage();

    for (let i = 1; i <= lastPage; i += 1) {
      pages.push(i);
    }

    return pages;
  }

  private emptyUsuarioDetailForm(): UsuarioDetailForm {
    return {
      uid_usuario: '',
      pwd_usuario: '',
      nombres_usuario: '',
      apellidos_usuario: '',
      telefono_usuario: '',
      email_usuario: '',
      id_autoridad: '',
      vigencia_usuario: '1',
    };
  }

  private mapUsuarioDetailToForm(usuario: UsuarioDetail): UsuarioDetailForm {
    return {
      uid_usuario: usuario.uid_usuario || '',
      pwd_usuario: '',
      nombres_usuario: usuario.nombres_usuario || '',
      apellidos_usuario: usuario.apellidos_usuario || '',
      telefono_usuario: usuario.telefono_usuario || '',
      email_usuario: usuario.email_usuario || '',
      id_autoridad: usuario.id_autoridad === null || usuario.id_autoridad === undefined ? '' : `${usuario.id_autoridad}`,
      vigencia_usuario: usuario.vigencia_usuario ? '1' : '0',
    };
  }

  private loadAutoridades(): void {
    this.usuariosApi.autoridades().subscribe({
      next: (response) => {
        this.autoridades.set(response);
      },
      error: () => {
        this.usuariosError.set('No se pudo cargar el catalogo de autoridades.');
      },
    });
  }

  private loadUsuarioUnidades(usuarioId: number, keepLoading = false): void {
    if (!keepLoading) {
      this.usuarioRelacionLoading.set(true);
    }

    this.usuariosApi.unidades(usuarioId).subscribe({
      next: (response) => {
        this.usuarioUnidades.set(response);
        this.usuarioRelacionLoading.set(false);
      },
      error: () => {
        this.usuarioRelacionLoading.set(false);
        this.usuarioDetailError.set('No se pudieron cargar las unidades relacionadas.');
      },
    });
  }

  private loadUnidadesCatalogo(): void {
    this.usuariosApi.unidadesCatalogo().subscribe({
      next: (response) => {
        this.unidadesCatalogo.set(response);
      },
      error: () => {
        this.usuarioDetailError.set('No se pudo cargar el catalogo de unidades.');
      },
    });
  }

  private loadUsuarios(page: number): void {
    this.usuariosLoading.set(true);
    this.usuariosError.set('');

    this.usuariosApi
      .list(this.usuarioSearch(), page, this.usuarioPerPage(), this.usuarioSortBy(), this.usuarioSortDir())
      .subscribe({
        next: (response) => {
          this.usuarios.set(response.data);
          this.usuarioPage.set(response.current_page);
          this.usuarioLastPage.set(response.last_page);
          this.usuarioTotal.set(response.total);
          this.usuarioPerPage.set(response.per_page);
          this.usuariosLoading.set(false);
        },
        error: () => {
          this.usuariosLoading.set(false);
          this.usuariosError.set('No se pudo cargar la lista de usuarios.');
        },
      });
  }

  private finishUnidadRelacionSuccess(usuarioId: number, message: string): void {
    this.usuarioRelacionConfirmSaving.set(false);
    this.usuarioRelacionLoading.set(false);
    this.closeUsuarioRelacionConfirmModal();
    this.usuarioUnidadSeleccionada.set('');
    this.usuarioDetailError.set('');
    this.usuariosError.set(message);
    this.loadUsuarioUnidades(usuarioId, true);
  }
}
