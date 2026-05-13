import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

export type LayoutSection =
  | 'dashboard'
  | 'unidades'
  | 'usuarios'
  | 'tickets'
  | 'actualizaciones'
  | 'kpis-rpt-dia'
  | 'kpis-rpt-rango';
export type BackendStatus = 'loading' | 'online' | 'offline';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './sidebar.component.html',
  styleUrl: './sidebar.component.scss',
})
export class SidebarComponent {
  @Input() isOpen = false;
  @Input() isCollapsed = false;
  @Input() currentSection: LayoutSection = 'dashboard';
  @Input() adminMenuOpen = false;
  @Input() sistemasMenuOpen = false;
  @Input() kpisMenuOpen = false;
  @Input() status: BackendStatus = 'loading';
  @Input() authUserUid = '';

  @Output() toggleCollapse = new EventEmitter<void>();
  @Output() toggleAdminMenu = new EventEmitter<void>();
  @Output() toggleSistemasMenu = new EventEmitter<void>();
  @Output() toggleKpisMenu = new EventEmitter<void>();
  @Output() openDashboard = new EventEmitter<void>();
  @Output() openUnidades = new EventEmitter<void>();
  @Output() openUsuarios = new EventEmitter<void>();
  @Output() openTickets = new EventEmitter<void>();
  @Output() openActualizaciones = new EventEmitter<void>();
  @Output() openKpiRptDia = new EventEmitter<void>();
  @Output() openKpiRptRango = new EventEmitter<void>();
  @Output() logout = new EventEmitter<void>();
  @Output() close = new EventEmitter<void>();

  protected onClose(): void {
    this.close.emit();
  }

  protected onToggleCollapse(): void {
    this.toggleCollapse.emit();
  }

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

  protected onOpenKpiRptDia(event: Event): void {
    event.preventDefault();
    this.openKpiRptDia.emit();
  }

  protected onOpenKpiRptRango(event: Event): void {
    event.preventDefault();
    this.openKpiRptRango.emit();
  }

  protected onLogout(): void {
    this.logout.emit();
  }
}

