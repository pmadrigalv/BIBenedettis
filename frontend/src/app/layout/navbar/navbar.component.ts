import { Component, EventEmitter, Output } from '@angular/core';

@Component({
  selector: 'app-navbar',
  standalone: true,
  templateUrl: './navbar.component.html',
})
export class NavbarComponent {
  @Output() toggleSidebar = new EventEmitter<void>();

  protected onToggleSidebar(): void {
    this.toggleSidebar.emit();
  }
}
