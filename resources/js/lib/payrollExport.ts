export type PayrollConceptBreakdown = {
    salary: number;
    overtime: number;
    bonus: number;
    delaysPenalty: number;
    absencesPenalty: number;
    imss: number;
    isr: number;
    customPerceptions?: number;
    customDeductions?: number;
    adjustmentReason?: string;
};

export type PayrollRow = {
    id: number;
    initials?: string;
    name: string;
    area?: string;
    dailyRate: number;
    hourlyOvertimeRate: number;
    days: number;
    delays: number;
    absences: number;
    extra: number;
    concepts?: PayrollConceptBreakdown;
    perceptions: number;
    deductions: number;
    isr?: number;
    imss?: number;
    net: number;
    status: 'Correcto' | 'Revisar';
    tone: 'green' | 'amber';
};

export type PayrollSummary = {
    employees: number;
    perceptions: number;
    deductions: number;
    isr?: number;
    imss?: number;
    net: number;
};

export type PayrollPeriod = {
    id: string;
    code?: string;
    label: string;
    frequency?: string;
    type?: string;
    status: string;
    tone: 'amber' | 'green' | 'cyan' | 'slate';
    startDate?: string;
    endDate?: string;
    paymentDate?: string;
    summary: PayrollSummary;
    rows: PayrollRow[];
};

export function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    }).format(Math.round(amount));
}

export function recalculateEmployeeRow(
    row: PayrollRow,
    overrides?: Partial<
        Pick<
            PayrollRow,
            'days' | 'delays' | 'absences' | 'extra' | 'status' | 'perceptions' | 'deductions'
        >
    > & {
        bonus?: number;
        customPerceptions?: number;
        customDeductions?: number;
        adjustmentReason?: string;
    },
): PayrollRow {
    const days = Math.max(0, Math.min(31, Number(overrides?.days ?? row.days)));
    const delays = Math.max(0, Number(overrides?.delays ?? row.delays));
    const absences = Math.max(0, Number(overrides?.absences ?? row.absences));
    const extra = Math.max(0, Number(overrides?.extra ?? row.extra));

    const bonus = Number(overrides?.bonus ?? row.concepts?.bonus ?? 0);
    const customPerceptions = Number(overrides?.customPerceptions ?? row.concepts?.customPerceptions ?? 0);
    const customDeductions = Number(overrides?.customDeductions ?? row.concepts?.customDeductions ?? 0);
    const adjustmentReason = overrides?.adjustmentReason ?? row.concepts?.adjustmentReason ?? '';

    // Percepciones desglosadas por concepto
    const salary = Math.round(days * row.dailyRate);
    const overtime = Math.round(extra * row.hourlyOvertimeRate);
    const calculatedPerceptions = salary + overtime + bonus + customPerceptions;

    const perceptions =
        overrides?.perceptions !== undefined
            ? Math.max(0, Math.round(overrides.perceptions))
            : calculatedPerceptions;

    // Deducciones desglosadas por concepto
    const delaysPenalty = delays * 50;
    const absencesPenalty = Math.round(absences * row.dailyRate);
    const imss = Math.round(salary * 0.02725);
    const isrRate = perceptions > 10000 ? 0.088 : 0.055;
    const isr = Math.round(Math.max(0, perceptions - imss) * isrRate);

    const calculatedDeductions =
        delaysPenalty + absencesPenalty + imss + isr + customDeductions;

    const deductions =
        overrides?.deductions !== undefined
            ? Math.max(0, Math.round(overrides.deductions))
            : calculatedDeductions;

    const net = Math.max(0, perceptions - deductions);
    const hasIncidences = delays > 0 || absences > 0 || customDeductions > 0;
    const status: 'Correcto' | 'Revisar' =
        overrides?.status ?? (hasIncidences ? 'Revisar' : 'Correcto');
    const tone: 'green' | 'amber' = status === 'Correcto' ? 'green' : 'amber';

    return {
        ...row,
        days,
        delays,
        absences,
        extra,
        concepts: {
            salary,
            overtime,
            bonus,
            delaysPenalty,
            absencesPenalty,
            imss,
            isr,
            customPerceptions,
            customDeductions,
            adjustmentReason,
        },
        perceptions,
        deductions,
        isr,
        imss,
        net,
        status,
        tone,
    };
}

export function exportPayrollToCsv(
    periodLabel: string,
    rows: PayrollRow[],
    summary: PayrollSummary,
): void {
    const headers = [
        'Empleado',
        'Área',
        'Días trabajados',
        'Retardos',
        'Faltas',
        'Horas extra',
        'Sueldo Base (MXN)',
        'Horas Extra $ (MXN)',
        'Bonos/Extras (MXN)',
        'Total Percepciones (MXN)',
        'Descuento Retardos (MXN)',
        'Descuento Faltas (MXN)',
        'IMSS Trabajador (MXN)',
        'ISR Retenido (MXN)',
        'Ajustes Manuales (MXN)',
        'Total Deducciones (MXN)',
        'Neto a Pagar (MXN)',
        'Estado',
    ];

    const csvRows = [
        [`Reporte Detallado de Pre-nómina — ${periodLabel}`],
        [
            `Fecha de Emisión: ${new Date().toLocaleDateString('es-MX')}`,
            `Total Empleados: ${summary.employees}`,
            `Percepciones Totales: ${formatCurrency(summary.perceptions)}`,
            `Deducciones Totales: ${formatCurrency(summary.deductions)}`,
            `ISR Total: ${formatCurrency(summary.isr ?? 0)}`,
            `IMSS Total: ${formatCurrency(summary.imss ?? 0)}`,
            `Neto Estimado: ${formatCurrency(summary.net)}`,
        ],
        [],
        headers,
        ...rows.map((r) => [
            r.name,
            r.area ?? 'General',
            String(r.days),
            String(r.delays),
            String(r.absences),
            String(r.extra),
            formatCurrency(r.concepts?.salary ?? r.days * r.dailyRate),
            formatCurrency(r.concepts?.overtime ?? r.extra * r.hourlyOvertimeRate),
            formatCurrency((r.concepts?.bonus ?? 0) + (r.concepts?.customPerceptions ?? 0)),
            formatCurrency(r.perceptions),
            formatCurrency(r.concepts?.delaysPenalty ?? r.delays * 50),
            formatCurrency(r.concepts?.absencesPenalty ?? r.absences * r.dailyRate),
            formatCurrency(r.imss ?? r.concepts?.imss ?? 0),
            formatCurrency(r.isr ?? r.concepts?.isr ?? 0),
            formatCurrency(r.concepts?.customDeductions ?? 0),
            formatCurrency(r.deductions),
            formatCurrency(r.net),
            r.status,
        ]),
    ];

    const content =
        '\uFEFF' +
        csvRows
            .map((line) =>
                line
                    .map((cell) => `"${String(cell).replace(/"/g, '""')}"`)
                    .join(','),
            )
            .join('\n');

    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const slug = periodLabel
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
    link.href = url;
    link.download = `prenomina-${slug || 'periodo'}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
