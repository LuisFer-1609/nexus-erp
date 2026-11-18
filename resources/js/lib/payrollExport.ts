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
    perceptions: number;
    deductions: number;
    net: number;
    status: 'Correcto' | 'Revisar';
    tone: 'green' | 'amber';
};

export type PayrollSummary = {
    employees: number;
    perceptions: number;
    deductions: number;
    net: number;
};

export type PayrollPeriod = {
    id: string;
    label: string;
    status: string;
    tone: 'amber' | 'green' | 'cyan' | 'slate';
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
    >,
): PayrollRow {
    const days = Math.max(0, Math.min(15, Number(overrides?.days ?? row.days)));
    const delays = Math.max(0, Number(overrides?.delays ?? row.delays));
    const absences = Math.max(0, Number(overrides?.absences ?? row.absences));
    const extra = Math.max(0, Number(overrides?.extra ?? row.extra));

    const basePerceptions = Math.round(
        days * row.dailyRate + extra * row.hourlyOvertimeRate,
    );
    const perceptions =
        overrides?.perceptions !== undefined
            ? Math.max(0, Math.round(overrides.perceptions))
            : basePerceptions;

    const baseFixedDeduction = 290;
    const delayPenalty = delays * 45;
    const absenceDeduction = absences * Math.round(row.dailyRate * 0.45);
    const calculatedDeductions =
        baseFixedDeduction + delayPenalty + absenceDeduction;

    const deductions =
        overrides?.deductions !== undefined
            ? Math.max(0, Math.round(overrides.deductions))
            : calculatedDeductions;

    const net = Math.max(0, perceptions - deductions);
    const hasIncidences = delays > 0 || absences > 0;
    const status: 'Correcto' | 'Revisar' =
        overrides?.status ?? (hasIncidences ? 'Revisar' : 'Correcto');
    const tone: 'green' | 'amber' = status === 'Correcto' ? 'green' : 'amber';

    return {
        ...row,
        days,
        delays,
        absences,
        extra,
        perceptions,
        deductions,
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
        'Días trabajados',
        'Retardos',
        'Faltas',
        'Horas extra',
        'Percepciones (MXN)',
        'Deducciones (MXN)',
        'Neto (MXN)',
        'Estado',
    ];

    const csvRows = [
        [`Reporte de Pre-nómina — ${periodLabel}`],
        [
            `Total Empleados: ${summary.employees}`,
            `Percepciones: ${formatCurrency(summary.perceptions)}`,
            `Deducciones: ${formatCurrency(summary.deductions)}`,
            `Neto Estimado: ${formatCurrency(summary.net)}`,
        ],
        [],
        headers,
        ...rows.map((r) => [
            r.name,
            String(r.days),
            String(r.delays),
            String(r.absences),
            String(r.extra),
            formatCurrency(r.perceptions),
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
        .replace(/(^-|-$)/g, '');
    link.href = url;
    link.setAttribute('download', `pre-nomina-${slug || 'periodo'}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
