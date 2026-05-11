import { SlicePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import {
  ActualizacionRow,
  ActualizacionSortBy,
  ActualizacionSortDir,
  ActualizacionesApiService,
  CrearActualizacionPayload,
} from '../../services/actualizaciones-api.service';

type ActualizacionForm = {
  titulo: string;
  descripcion: string;
  version: string;
  fecha_publicacion: string;
};

@Component({
  selector: 'app-admin-actualizaciones',
  standalone: true,
  imports: [SlicePipe],
  templateUrl: './admin-actualizaciones.component.html',
})
export class AdminActualizacionesComponent {
  private readonly actualizacionesApi = inject(ActualizacionesApiService);

  protected readonly actualizaciones = signal<ActualizacionRow[]>([]);
  protected readonly loading = signal(false);
  protected readonly error = signal('');
  protected readonly search = signal('');
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly total = signal(0);
  protected readonly perPage = signal(10);
  protected readonly sortBy = signal<ActualizacionSortBy>('id');
  protected readonly sortDir = signal<ActualizacionSortDir>('desc');
  protected readonly modalOpen = signal(false);
  protected readonly saving = signal(false);
  protected readonly form = signal<ActualizacionForm>({
    titulo: '',
    descripcion: '',
    version: '',
    fecha_publicacion: '',
  });

  constructor() {
    this.loadActualizaciones(1);
  }

  protected onSearchInput(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
  }

  protected doSearch(): void {
    this.loadActualizaciones(1);
  }

  protected goToPage(p: number): void {
    if (p < 1 || p > this.lastPage() || p === this.page()) return;
    this.loadActualizaciones(p);
  }

  protected sortBy_(field: ActualizacionSortBy): void {
    if (this.sortBy() === field) {
      this.sortDir.set(this.sortDir() === 'asc' ? 'desc' : 'asc');
    } else {
      this.sortBy.set(field);
      this.sortDir.set('asc');
    }
    this.loadActualizaciones(1);
  }

  protected sortLabel(field: ActualizacionSortBy): string {
    if (this.sortBy() !== field) return '';
    return this.sortDir() === 'asc' ? '↑' : '↓';
  }

  protected openModal(): void {
    this.form.set({ titulo: '', descripcion: '', version: '', fecha_publicacion: '' });
    this.modalOpen.set(true);
  }

  protected closeModal(): void {
    this.modalOpen.set(false);
  }

  protected updateForm(field: keyof ActualizacionForm, event: Event): void {
    const value = (event.target as HTMLInputElement | HTMLTextAreaElement).value;
    this.form.update((f) => ({ ...f, [field]: value }));
  }

  protected saveActualizacion(): void {
    const f = this.form();
    if (!f.titulo.trim()) return;

    const payload: CrearActualizacionPayload = {
      titulo: f.titulo.trim(),
      descripcion: f.descripcion.trim() || null,
      version: f.version.trim() || null,
      fecha_publicacion: f.fecha_publicacion || null,
    };

    this.saving.set(true);
    this.actualizacionesApi.create(payload).subscribe({
      next: () => {
        this.saving.set(false);
        this.modalOpen.set(false);
        this.loadActualizaciones(1);
      },
      error: () => {
        this.saving.set(false);
      },
    });
  }

  private loadActualizaciones(p: number): void {
    this.loading.set(true);
    this.error.set('');
    this.actualizacionesApi.list(this.search(), p, this.perPage(), this.sortBy(), this.sortDir()).subscribe({
      next: (res) => {
        this.actualizaciones.set(res.data);
        this.page.set(res.current_page);
        this.lastPage.set(res.last_page);
        this.total.set(res.total);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Error al cargar actualizaciones.');
        this.loading.set(false);
      },
    });
  }
}
