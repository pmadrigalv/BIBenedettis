import { Component, inject, signal } from '@angular/core';
import {
  AutoridadOption,
  UsuarioRow,
  UsuarioSortBy,
  UsuarioSortDir,
  UsuariosApiService,
} from '../../services/usuarios-api.service';

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

@Component({
  selector: 'app-admin-usuarios',
  standalone: true,
  templateUrl: './admin-usuarios.component.html',
})
export class AdminUsuariosComponent {
  private readonly usuariosApi = inject(UsuariosApiService);

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

  protected onUsuarioFormChange(field: keyof UsuarioForm, event: Event): void {
    const target = event.target as HTMLInputElement | HTMLSelectElement;
    const value = target.value;
    this.usuarioForm.update((current) => ({ ...current, [field]: value }));
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

  protected usuarioPages(): number[] {
    const pages: number[] = [];
    const lastPage = this.usuarioLastPage();

    for (let i = 1; i <= lastPage; i += 1) {
      pages.push(i);
    }

    return pages;
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
}
