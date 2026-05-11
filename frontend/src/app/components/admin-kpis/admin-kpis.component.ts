import { Component, Input } from '@angular/core';

export type KpiReportType = 'vta-orilla' | 'vta-pizzas' | 'vta-adicionales' | 'rpt-vtas' | 'rpt-malas-ordenes';

@Component({
  selector: 'app-admin-kpis',
  standalone: true,
  templateUrl: './admin-kpis.component.html',
})
export class AdminKpisComponent {
  @Input() reportType: KpiReportType = 'vta-orilla';

  protected reportTitle(): string {
    const map: Record<KpiReportType, string> = {
      'vta-orilla': 'VTA ORILLA',
      'vta-pizzas': 'VTA PIZZAS',
      'vta-adicionales': 'VTA ADICIONALES',
      'rpt-vtas': 'RPT VTAS',
      'rpt-malas-ordenes': 'RPT MALAS ORDENES',
    };

    return map[this.reportType];
  }
}
