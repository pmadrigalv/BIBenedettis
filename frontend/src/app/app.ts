import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { AdminActualizacionesComponent } from './components/admin-actualizaciones/admin-actualizaciones.component';
import { AdminKpisComponent } from './components/admin-kpis/admin-kpis.component';
import { AdminTicketsComponent } from './components/admin-tickets/admin-tickets.component';
import { AdminUnidadesComponent } from './components/admin-unidades/admin-unidades.component';
import { AdminUsuariosComponent } from './components/admin-usuarios/admin-usuarios.component';
import { NavbarComponent } from './layout/navbar/navbar.component';
import { SidebarComponent } from './layout/sidebar/sidebar.component';
import { AuthService } from './services/auth.service';

type KpiReportType = 'vta-orilla' | 'vta-pizzas' | 'vta-adicionales' | 'rpt-vtas' | 'rpt-malas-ordenes';

type HealthResponse = {
  app: string;
  laravel: string;
  php: string;
  database: {
    connection: string;
    status: string;
  };
  timestamp: string;
};

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [AdminUnidadesComponent, AdminUsuariosComponent, AdminTicketsComponent, AdminActualizacionesComponent, AdminKpisComponent, SidebarComponent, NavbarComponent],
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class App {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  protected readonly title = 'Angular + Laravel + MySQL';
  protected readonly authUser = this.auth.user;
  protected readonly isAuthenticated = this.auth.isAuthenticated;
  protected readonly sessionLoading = signal(true);
  protected readonly loginError = signal('');
  protected readonly loginSubmitting = signal(false);
  protected readonly loginUid = signal('');
  protected readonly loginPwd = signal('');
  protected readonly sidebarOpen = signal(
    typeof window !== 'undefined' && window.innerWidth >= 1024
  );
  protected readonly sidebarCollapsed = signal(false);
  protected readonly adminMenuOpen = signal(false);
  protected readonly sistemasMenuOpen = signal(false);
  protected readonly kpisMenuOpen = signal(false);
  protected readonly currentSection = signal<
    'dashboard'
    | 'unidades'
    | 'usuarios'
    | 'tickets'
    | 'actualizaciones'
    | 'kpis-vta-orilla'
    | 'kpis-vta-pizzas'
    | 'kpis-vta-adicionales'
    | 'kpis-rpt-vtas'
    | 'kpis-rpt-malas-ordenes'
  >('dashboard');
  protected readonly currentKpiReport = signal<KpiReportType>('vta-orilla');
  protected readonly health = signal<HealthResponse | null>(null);
  protected readonly status = signal<'loading' | 'online' | 'offline'>('loading');
  protected readonly errorMessage = signal('');

  constructor() {
    this.auth.restoreSession().subscribe(() => {
      if (this.isAuthenticated()) {
        this.loadHealth();
      }

      this.sessionLoading.set(false);
    });
  }

  protected onLoginUidInput(event: Event): void {
    this.loginUid.set((event.target as HTMLInputElement).value);
  }

  protected onLoginPwdInput(event: Event): void {
    this.loginPwd.set((event.target as HTMLInputElement).value);
  }

  protected login(): void {
    const uid = this.loginUid().trim();
    const pwd = this.loginPwd();

    if (!uid || !pwd) {
      this.loginError.set('Usuario y contraseña son obligatorios.');
      return;
    }

    this.loginSubmitting.set(true);
    this.loginError.set('');

    this.auth.login(uid, pwd).subscribe({
      next: () => {
        this.loginSubmitting.set(false);
        this.loginPwd.set('');
        this.loadHealth();
      },
      error: (err) => {
        this.loginSubmitting.set(false);
        this.loginError.set(err?.error?.message ?? 'No fue posible iniciar sesión.');
      }
    });
  }

  protected logout(): void {
    this.auth.logout().subscribe(() => {
      this.currentSection.set('dashboard');
      this.health.set(null);
      this.status.set('loading');
      this.sidebarOpen.set(false);
    });
  }

  protected toggleSidebar(): void {
    this.sidebarOpen.update((value) => !value);
  }

  protected closeSidebar(): void {
    // Only close the sidebar overlay on mobile; on desktop it stays open
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
      this.sidebarOpen.set(false);
    }
  }

  protected toggleSidebarCollapse(): void {
    this.sidebarCollapsed.update((value) => !value);
  }

  protected reload(): void {
    if (!this.isAuthenticated()) {
      return;
    }

    this.loadHealth();
  }

  protected toggleAdminMenu(): void {
    this.adminMenuOpen.update((value) => !value);
  }

  protected toggleSistemasMenu(): void {
    this.sistemasMenuOpen.update((value) => !value);
  }

  protected toggleKpisMenu(): void {
    this.kpisMenuOpen.update((value) => !value);
  }

  protected openDashboard(event?: Event): void {
    event?.preventDefault();
    this.currentSection.set('dashboard');
    this.closeSidebar();
  }

  protected openUnidades(event?: Event): void {
    event?.preventDefault();
    this.currentSection.set('unidades');
    this.closeSidebar();
  }

  protected openUsuarios(event?: Event): void {
    event?.preventDefault();
    this.currentSection.set('usuarios');
    this.closeSidebar();
  }

  protected openTickets(event?: Event): void {
    event?.preventDefault();
    this.currentSection.set('tickets');
    this.closeSidebar();
  }

  protected openActualizaciones(event?: Event): void {
    event?.preventDefault();
    this.currentSection.set('actualizaciones');
    this.closeSidebar();
  }

  protected openKpiVtaOrilla(event?: Event): void {
    event?.preventDefault();
    this.currentKpiReport.set('vta-orilla');
    this.currentSection.set('kpis-vta-orilla');
    this.closeSidebar();
  }

  protected openKpiVtaPizzas(event?: Event): void {
    event?.preventDefault();
    this.currentKpiReport.set('vta-pizzas');
    this.currentSection.set('kpis-vta-pizzas');
    this.closeSidebar();
  }

  protected openKpiVtaAdicionales(event?: Event): void {
    event?.preventDefault();
    this.currentKpiReport.set('vta-adicionales');
    this.currentSection.set('kpis-vta-adicionales');
    this.closeSidebar();
  }

  protected openKpiRptVtas(event?: Event): void {
    event?.preventDefault();
    this.currentKpiReport.set('rpt-vtas');
    this.currentSection.set('kpis-rpt-vtas');
    this.closeSidebar();
  }

  protected openKpiRptMalasOrdenes(event?: Event): void {
    event?.preventDefault();
    this.currentKpiReport.set('rpt-malas-ordenes');
    this.currentSection.set('kpis-rpt-malas-ordenes');
    this.closeSidebar();
  }

  private loadHealth(): void {
    this.status.set('loading');
    this.errorMessage.set('');

    this.http.get<HealthResponse>('/api/health').subscribe({
      next: (response) => {
        this.health.set(response);
        this.status.set('online');
      },
      error: () => {
        this.health.set(null);
        this.status.set('offline');
        this.errorMessage.set('No se pudo alcanzar la API de Laravel a traves del proxy de Angular.');
      }
    });
  }
}
