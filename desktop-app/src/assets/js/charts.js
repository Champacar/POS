/**
 * Charts JavaScript
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

document.addEventListener('DOMContentLoaded', function () {
    // Global Chart.js defaults
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Noto Sans Lao', 'Segoe UI', sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#546e7a';
    }

    // Sales Chart (Dashboard)
    const salesChartEl = document.getElementById('salesChart');
    if (salesChartEl) {
        new Chart(salesChartEl, {
            type: 'bar',
            data: {
                labels: window.salesChartLabels || ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],
                datasets: [{
                    label: 'ຍອດຂາຍ',
                    data: window.salesChartData || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: '#4caf50',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#263238',
                        cornerRadius: 8,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return formatCurrency(context.parsed.y) + ' ກີບ';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Income/Expense Chart
    const incExpChartEl = document.getElementById('incomeExpenseChart');
    if (incExpChartEl) {
        new Chart(incExpChartEl, {
            type: 'bar',
            data: {
                labels: window.incExpChartLabels || ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],
                datasets: [
                    {
                        label: 'ລາຍຮັບ',
                        data: window.incExpChartIncome || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(76, 175, 80, 0.7)',
                        borderColor: '#4caf50',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.5,
                    },
                    {
                        label: 'ລາຍຈ່າຍ',
                        data: window.incExpChartExpense || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(229, 115, 115, 0.7)',
                        borderColor: '#e57373',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.5,
                    },
                    {
                        label: 'ກຳໄລ',
                        data: window.incExpChartProfit || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(66, 165, 245, 0.7)',
                        borderColor: '#42a5f5',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#263238',
                        cornerRadius: 8,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatCurrency(context.parsed.y) + ' ກີບ';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});

function formatCurrency(amount) {
    return Number(amount).toLocaleString('en-US');
}
