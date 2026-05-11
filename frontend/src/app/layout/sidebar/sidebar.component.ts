import { Component, EventEmitter, Input, Output } from '@angular/core';

export type LayoutSection =
  | 'dashboard'
  | 'unidades'
  | 'usuarios'
  | 'tickets'
  | 'actualizaciones'
  | 'kpis-vta-orilla'
  | 'kpis-vta-pizzas'
  | 'kpis-vta-adicionales'
  | 'kpis-rpt-vtas'
  | 'kpis-rpt-malas-ordenes';
export type BackendStatus = 'loading' | 'online' | 'offline';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  templateUrl: './sidebar.component.html',
})
export class SidebarComponent {
  @Input() currentSection: LayoutSection = 'dashboard';
  @Input() adminMenuOpen = false;
  @Input() sistemasMenuOpen = false;
  @Input() kpisMenuOpen = false;
  @Input() status: BackendStatus = 'loading';
  @Input() authUserUid = '';

  @Output() toggleAdminMenu = new EventEmitter<void>();
  @Output() toggleSistemasMenu = new EventEmitter<void>();
  @Output() toggleKpisMenu = new EventEmitter<void>();
  @Output() openDashboard = new EventEmitter<void>();
  @Output() openUnidades = new EventEmitter<void>();
  @Output() openUsuarios = new EventEmitter<void>();
  @Output() openTickets = new EventEmitter<void>();
  @Output() openActualizaciones = new EventEmitter<void>();
  @Output() openKpiVtaOrilla = new EventEmitter<void>();
  @Output() openKpiVtaPizzas = new EventEmitter<void>();
  @Output() openKpiVtaAdicionales = new EventEmitter<void>();
  @Output() openKpiRptVtas = new EventEmitter<void>();
  @Output() openKpiRptMalasOrdenes = new EventEmitter<void>();
  @Output() logout = new EventEmitter<void>();

  protected onToggleAdminMenu(): void {
    this.toggleAdminMenu.emit();
  }

  protected onToggleSistemasMenu(): void {
    this.toggleSistemasMenu.emit();
  }

  protected onToggleKpisMenu(): void {
    this.toggleKpisMenu.emit();
  }

  protected onOpenDashboard(event: Event): void {
    event.preventDefault();
    this.openDashboard.emit();
  }

  protected onOpenUnidades(event: Event): void {
    event.preventDefault();
    this.openUnidades.emit();
  }

  protected onOpenUsuarios(event: Event): void {
    event.preventDefault();
    this.openUsuarios.emit();
  }

  protected onOpenTickets(event: Event): void {
    event.preventDefault();
    this.openTickets.emit();
  }

  protected onOpenActualizaciones(event: Event): void {
    event.preventDefault();
    this.openActualizaciones.emit();
  }

  protected onOpenKpiVtaOrilla(event: Event): void {
    event.preventDefault();
    this.openKpiVtaOrilla.emit();
  }

  protected onOpenKpiVtaPizzas(event: Event): void {
    event.preventDefault();
    this.openKpiVtaPizzas.emit();
  }

  protected onOpenKpiVtaAdicionales(event: Event): void {
    event.preventDefault();
    this.openKpiVtaAdicionales.emit();
  }

  protected onOpenKpiRptVtas(event: Event): void {
    event.preventDefault();
    this.openKpiRptVtas.emit();
  }

  protected onOpenKpiRptMalasOrdenes(event: Event): void {
    event.preventDefault();
    this.openKpiRptMalasOrdenes.emit();
  }

  protected onLogout(): void {
    this.logout.emit();
  }
}

