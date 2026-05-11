import { SlicePipe } from '@angular/common';
import { Component, effect, inject, signal } from '@angular/core';
import {
  CrearTicketPayload,
  TicketRow,
  TicketSortBy,
  TicketSortDir,
  TicketsApiService,
} from '../../services/tickets-api.service';
import {
  SistemaUsuarioOption,
  UsuarioUnidadOption,
  UsuariosApiService,
} from '../../services/usuarios-api.service';
import { AuthService } from '../../services/auth.service';

type TicketForm = {
  titulo: string;
  fecha: string;
  descripcion: string;
  estado: string;
  prioridad: string;
  usuario_id: string;
  unidad_id: string;
  imagenes: File[];
  archivo: File | null;
};

@Component({
  selector: 'app-admin-tickets',
  standalone: true,
  imports: [SlicePipe],
  templateUrl: './admin-tickets.component.html',
})
export class AdminTicketsComponent {
  private readonly ticketsApi = inject(TicketsApiService);
  private readonly usuariosApi = inject(UsuariosApiService);
  private readonly auth = inject(AuthService);

  protected readonly tickets = signal<TicketRow[]>([]);
  protected readonly loading = signal(false);
  protected readonly error = signal('');
  protected readonly search = signal('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly total = signal(0);
  protected readonly perPage = signal(10);
  protected readonly sortBy = signal<TicketSortBy>('id');
  protected readonly sortDir = signal<TicketSortDir>('desc');
  protected readonly selectedTicket = signal<TicketRow | null>(null);
  protected readonly modalOpen = signal(false);
  protected readonly saving = signal(false);
  protected readonly loadingUsuarios = signal(false);
  protected readonly usuariosSistemas = signal<SistemaUsuarioOption[]>([]);
  protected readonly tecnicosSistemas = signal<SistemaUsuarioOption[]>([]);
  protected readonly loadingUnidades = signal(false);
  protected readonly unidadesUsuario = signal<UsuarioUnidadOption[]>([]);
  protected readonly loadingTecnicos = signal(false);
  protected readonly assigningTecnico = signal(false);
  protected readonly tecnicoSeleccionado = signal('');
  protected readonly asignacionTecnicoError = signal('');
  protected readonly asignacionTecnicoOk = signal('');
  protected readonly form = signal<TicketForm>({
    titulo: '',
    fecha: this.today(),
    descripcion: '',
    estado: 'abierto',
    prioridad: 'media',
    usuario_id: '',
    unidad_id: '',
    imagenes: [],
    archivo: null,
  });

  constructor() {
    this.loadTickets(1);
    this.loadSolicitantes();

    effect(() => {
      const isModalOpen = this.modalOpen();
      const currentUser = this.auth.user();

      if (!isModalOpen || !currentUser || this.currentUserIsSistemas()) {
        return;
      }

      const currentUserId = String(currentUser.id_usuario);
      if (this.form().usuario_id === currentUserId) {
        return;
      }

      this.form.update((f) => ({ ...f, usuario_id: currentUserId, unidad_id: '' }));
      this.unidadesUsuario.set([]);
      this.loadUnidadesUsuario(Number.parseInt(currentUserId, 10));
    });
  }

  protected currentUserIsSistemas(): boolean {
    const autoridad = (this.auth.user()?.autoridad ?? '').trim().toLowerCase();
    return autoridad.includes('sistemas');
  }

  protected currentUserIsRootOrGerenteSistemas(): boolean {
    const autoridad = (this.auth.user()?.autoridad ?? '').trim().toLowerCase();
    return autoridad === 'root' || (autoridad.includes('gerente') && autoridad.includes('sistemas'));
  }

  protected canEditSolicitante(): boolean {
    return this.currentUserIsSistemas() || this.currentUserIsRootOrGerenteSistemas();
  }

  protected canAssignToSistemasUser(): boolean {
    return this.currentUserIsRootOrGerenteSistemas();
  }

  protected canAssignTecnico(): boolean {
    return this.auth.user() !== null;
  }

  protected tecnicoOptions(): SistemaUsuarioOption[] {
    return this.tecnicosSistemas();
  }

  protected currentUserDisplayName(): string {
    const currentUser = this.auth.user();
    if (!currentUser) {
      return '';
    }

    return currentUser.nombre || currentUser.uid_usuario;
  }

  protected solicitanteOptions(): SistemaUsuarioOption[] {
    const currentUser = this.auth.user();
    const options = this.usuariosSistemas();

    if (!currentUser) {
      return options;
    }

    const exists = options.some((option) => option.id_usuario === currentUser.id_usuario);
    if (exists) {
      return options;
    }

    return [
      {
        id_usuario: currentUser.id_usuario,
        nombre: currentUser.nombre,
        autoridad: currentUser.autoridad,
      },
      ...options,
    ];
  }

  protected visibleSolicitanteOptions(): SistemaUsuarioOption[] {
    const options = this.solicitanteOptions();

    if (this.canAssignToSistemasUser()) {
      return options;
    }

    return options.filter((option) => (option.autoridad ?? '').trim().toLowerCase() !== 'sistemas');
  }

  protected onSearchInput(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
  }

  protected doSearch(): void {
    this.loadTickets(1);
  }

  protected goToPage(p: number): void {
    if (p < 1 || p > this.lastPage() || p === this.page()) return;
    this.loadTickets(p);
  }

  protected sortBy_(field: TicketSortBy): void {
    if (this.sortBy() === field) {
      this.sortDir.set(this.sortDir() === 'asc' ? 'desc' : 'asc');
    } else {
      this.sortBy.set(field);
      this.sortDir.set('asc');
    }
    this.loadTickets(1);
  }

  protected sortLabel(field: TicketSortBy): string {
    if (this.sortBy() !== field) return '';
    return this.sortDir() === 'asc' ? '↑' : '↓';
  }

  protected openModal(): void {
    const currentUserId = this.auth.user()?.id_usuario;
    const shouldClearDefault = this.currentUserIsSistemas() && !this.canAssignToSistemasUser();
    const defaultSolicitante = currentUserId && !shouldClearDefault ? String(currentUserId) : '';

    this.form.set({
      titulo: '',
      fecha: this.today(),
      descripcion: '',
      estado: 'abierto',
      prioridad: 'media',
      usuario_id: defaultSolicitante,
      unidad_id: '',
      imagenes: [],
      archivo: null,
    });
    this.unidadesUsuario.set([]);
    this.modalOpen.set(true);

    if (defaultSolicitante) {
      this.loadUnidadesUsuario(Number.parseInt(defaultSolicitante, 10));
    }

    if (this.usuariosSistemas().length === 0) {
      this.loadSolicitantes();
    }
  }

  protected examinarTicket(ticket: TicketRow): void {
    this.selectedTicket.set(ticket);
    this.tecnicoSeleccionado.set(ticket.tecnico_id ? String(ticket.tecnico_id) : '');
    this.asignacionTecnicoError.set('');
    this.asignacionTecnicoOk.set('');

    if (this.tecnicoOptions().length === 0) {
      this.loadTecnicosSistemas();
    }
  }

  protected cerrarDetalle(): void {
    this.selectedTicket.set(null);
    this.tecnicoSeleccionado.set('');
    this.asignacionTecnicoError.set('');
    this.asignacionTecnicoOk.set('');
  }

  protected onTecnicoChange(event: Event): void {
    this.tecnicoSeleccionado.set((event.target as HTMLSelectElement).value);
    this.asignacionTecnicoError.set('');
    this.asignacionTecnicoOk.set('');
  }

  protected asignarTecnico(): void {
    const detalle = this.selectedTicket();
    const tecnicoId = Number.parseInt(this.tecnicoSeleccionado(), 10);

    if (!detalle || Number.isNaN(tecnicoId)) {
      this.asignacionTecnicoError.set('Selecciona un técnico válido.');
      return;
    }

    this.assigningTecnico.set(true);
    this.asignacionTecnicoError.set('');
    this.asignacionTecnicoOk.set('');

    this.ticketsApi.assignTecnico(detalle.id, tecnicoId).subscribe({
      next: (ticketActualizado) => {
        this.assigningTecnico.set(false);
        this.selectedTicket.set(ticketActualizado);
        this.tecnicoSeleccionado.set(String(ticketActualizado.tecnico_id ?? ''));
        this.asignacionTecnicoOk.set('Técnico asignado correctamente.');

        this.tickets.update((items) =>
          items.map((ticket) => (ticket.id === ticketActualizado.id ? ticketActualizado : ticket)),
        );
      },
      error: () => {
        this.assigningTecnico.set(false);
        this.asignacionTecnicoError.set('No se pudo asignar el técnico.');
      },
    });
  }

  protected closeModal(): void {
    this.unidadesUsuario.set([]);
    this.modalOpen.set(false);
  }

  protected onUsuarioChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.form.update((f) => ({ ...f, usuario_id: value, unidad_id: '' }));
    this.unidadesUsuario.set([]);

    if (!value) {
      return;
    }

    this.loadUnidadesUsuario(Number.parseInt(value, 10));
  }

  protected updateForm(field: keyof TicketForm, event: Event): void {
    const value = (event.target as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).value;
    this.form.update((f) => ({ ...f, [field]: value }));
  }

  protected saveTicket(): void {
    const f = this.form();
    if (!f.titulo.trim() || !f.fecha || !f.usuario_id) return;
    if (this.unidadesUsuario().length > 0 && !f.unidad_id) return;

    const unidadId = f.unidad_id === 'all' || f.unidad_id === '' ? null : Number.parseInt(f.unidad_id, 10);
    const usuarioId = this.currentUserIsSistemas()
      ? Number.parseInt(f.usuario_id, 10)
      : (this.auth.user()?.id_usuario ?? Number.parseInt(f.usuario_id, 10));

    const payload: CrearTicketPayload = {
      titulo: f.titulo.trim(),
      fecha: f.fecha,
      descripcion: f.descripcion.trim() || null,
      estado: f.estado,
      prioridad: f.prioridad,
      usuario_id: usuarioId,
      unidad_id: unidadId,
      imagenes: f.imagenes,
      archivo: f.archivo,
    };

    this.saving.set(true);
    this.ticketsApi.create(payload).subscribe({
      next: () => {
        this.saving.set(false);
        this.modalOpen.set(false);
        this.loadTickets(1);
      },
      error: () => {
        this.saving.set(false);
      },
    });
  }

  protected onImagesSelected(event: Event): void {
    const files = (event.target as HTMLInputElement).files;
    if (!files || files.length === 0) {
      return;
    }

    this.form.update((f) => ({
      ...f,
      imagenes: [...f.imagenes, ...Array.from(files)],
    }));

    (event.target as HTMLInputElement).value = '';
  }

  protected removeImage(index: number): void {
    this.form.update((f) => ({
      ...f,
      imagenes: f.imagenes.filter((_, i) => i !== index),
    }));
  }

  protected onArchivoSelected(event: Event): void {
    const files = (event.target as HTMLInputElement).files;

    this.form.update((f) => ({
      ...f,
      archivo: files && files.length > 0 ? files[0] : null,
    }));
  }

  private loadTickets(p: number): void {
    this.loading.set(true);
    this.error.set('');
    this.ticketsApi.list(this.search(), p, this.perPage(), this.sortBy(), this.sortDir()).subscribe({
      next: (res) => {
        this.tickets.set(res.data);
        this.page.set(res.current_page);
        this.lastPage.set(res.last_page);
        this.total.set(res.total);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Error al cargar tickets.');
        this.loading.set(false);
      },
    });
  }

  private loadSolicitantes(): void {
    this.loadingUsuarios.set(true);
    const request$ = this.canEditSolicitante()
      ? this.usuariosApi.todos()
      : this.usuariosApi.solicitantes();

    request$.subscribe({
      next: (usuarios) => {
        this.usuariosSistemas.set(usuarios);
        this.loadingUsuarios.set(false);
      },
      error: () => {
        this.loadingUsuarios.set(false);
      },
    });
  }

  private loadTecnicosSistemas(): void {
    this.loadingTecnicos.set(true);
    this.usuariosApi.sistemas().subscribe({
      next: (usuarios) => {
        this.tecnicosSistemas.set(usuarios);
        this.loadingTecnicos.set(false);
      },
      error: () => {
        this.tecnicosSistemas.set([]);
        this.loadingTecnicos.set(false);
      },
    });
  }

  private loadUnidadesUsuario(usuarioId: number): void {
    this.loadingUnidades.set(true);
    this.usuariosApi.unidadesPorUsuario(usuarioId).subscribe({
      next: (unidades) => {
        this.unidadesUsuario.set(unidades);
        this.loadingUnidades.set(false);
      },
      error: () => {
        this.unidadesUsuario.set([]);
        this.loadingUnidades.set(false);
      },
    });
  }

  private today(): string {
    return new Date().toISOString().slice(0, 10);
  }

  protected estadoLabel(estado: string): string {
    const map: Record<string, string> = {
      abierto: 'Abierto',
      en_proceso: 'En proceso',
      resuelto: 'Resuelto',
      cerrado: 'Cerrado',
    };
    return map[estado] ?? estado;
  }

  protected prioridadLabel(prioridad: string): string {
    const map: Record<string, string> = {
      baja: 'Baja',
      media: 'Media',
      alta: 'Alta',
      urgente: 'Urgente',
    };
    return map[prioridad] ?? prioridad;
  }

  protected prioridadClass(prioridad: string): string {
    const map: Record<string, string> = {
      baja: 'bg-slate-100 text-slate-600',
      media: 'bg-blue-100 text-blue-700',
      alta: 'bg-amber-100 text-amber-700',
      urgente: 'bg-rose-100 text-rose-700',
    };
    return map[prioridad] ?? '';
  }
}
