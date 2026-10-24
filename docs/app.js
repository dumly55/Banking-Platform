// Fills the page from data.json, which build.py writes from the SQL analysis.

const money = (value) =>
    value >= 1e6 ? `$${+(value / 1e6).toFixed(1)}M` : value >= 1e3 ? `$${Math.round(value / 1e3)}K` : `$${Math.round(value)}`;
const dollars = (value) => `$${Math.round(value).toLocaleString('en-US')}`;
const count = (value) => value.toLocaleString('en-US');
const percent = (share, digits = 0) => `${(share * 100).toFixed(digits)}%`;
const asDate = (isoDate) => new Date(`${isoDate.slice(0, 10)}T00:00`);
const monthName = (isoDate) => asDate(isoDate).toLocaleDateString('en-US', { month: 'long' });
const shortMonth = (isoDate) => asDate(isoDate).toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

function fill(name, text) {
    document.querySelectorAll(`[data-fill="${name}"]`).forEach((el) => { el.textContent = text; });
}

function showKpis(data) {
    const { totals } = data;
    const growth = data['01_monthly_growth'];
    const lastMonth = growth.at(-1);
    const dormant = data['05_dormant_money'].find((row) => row.inactive_for === 'All dormant');

    fill('as_of', asDate(totals.as_of).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
    fill('money_held', money(totals.money_held));
    fill('money_note', `Up ${lastMonth.money_growth_pct}% in ${monthName(lastMonth.month)}`);
    fill('first_money', money(growth[0].money_held));
    fill('customers', count(totals.customers));
    fill('customers_note', `${lastMonth.new_customers} joined in ${monthName(lastMonth.month)}`);
    fill('active_share', percent(totals.active_customers / totals.customers));
    fill('active_note', `${count(totals.active_customers)} moved money in the last 90 days`);
    fill('dormant_money', money(dormant.money_held));
    fill('dormant_note', `${dormant.share_of_all_money_pct}% of all money, with ${dormant.customers} customers`);
}

// The table view behind each chart, for screen readers and anyone who wants the numbers.
function addTable(figureId, headers, rows) {
    const cells = (row, tag) => row.map((cell) => `<${tag}>${cell}</${tag}>`).join('');
    const details = document.createElement('details');
    details.innerHTML = `<summary>Show the data</summary><div class="table-scroll"><table>
        <thead><tr>${cells(headers, 'th')}</tr></thead>
        <tbody>${rows.map((row) => `<tr>${cells(row, 'td')}</tr>`).join('')}</tbody></table></div>`;
    document.getElementById(figureId).append(details);
}

function moneyByType(data) {
    const months = [...new Set(data.money_by_type.map((row) => row.month))];
    const series = (type) => months.map((month) =>
        data.money_by_type.find((row) => row.month === month && row.account_type === type)?.money_held ?? 0);
    return { months, checking: series('Checking'), savings: series('Savings') };
}

function addTables(data) {
    const { months, checking, savings } = moneyByType(data);
    addTable('money-figure', ['Month', 'Checking', 'Savings', 'Total'],
        months.map((m, i) => [shortMonth(m), dollars(checking[i]), dollars(savings[i]), dollars(checking[i] + savings[i])]));
    addTable('signups-figure', ['Month', 'New customers'],
        data['01_monthly_growth'].map((row) => [shortMonth(row.month), row.new_customers]));
}

// Charts read their colors from the CSS tokens, so they're rebuilt when light/dark mode changes.
const charts = [];

function drawCharts(data) {
    charts.splice(0).forEach((chart) => chart.destroy());
    Chart.defaults.font.family = '"IBM Plex Sans", system-ui, sans-serif';
    Chart.defaults.color = css('--ink-2');
    Chart.defaults.borderColor = css('--grid');
    Object.assign(Chart.defaults.plugins.tooltip, {
        backgroundColor: css('--ink'), titleColor: css('--surface'), bodyColor: css('--surface'),
        padding: 10, cornerRadius: 6, boxPadding: 4,
    });
    Object.assign(Chart.defaults.plugins.legend.labels, { usePointStyle: true, boxHeight: 8 });
    Chart.defaults.maintainAspectRatio = false;
    const axis = { border: { color: css('--axis') } };
    const noGrid = { ...axis, grid: { display: false } };
    const make = (id, config) => charts.push(new Chart(document.getElementById(id), config));

    const { months, checking, savings } = moneyByType(data);
    // Stacked: checking fills down to zero, savings only fills its band above checking
    const area = (label, values, color, fill) => ({
        label, data: values, borderColor: color, backgroundColor: `${color}2e`, fill,
        borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderColor: css('--surface'), pointHoverBorderWidth: 2,
    });
    make('money-chart', {
        type: 'line',
        data: { labels: months.map(shortMonth), datasets: [area('Checking', checking, css('--series-1'), 'origin'), area('Savings', savings, css('--series-2'), '-1')] },
        options: {
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { ...noGrid, ticks: { maxTicksLimit: 8 } },
                y: { ...axis, stacked: true, ticks: { callback: money, maxTicksLimit: 6 } },
            },
            plugins: {
                legend: {
                    position: 'top', align: 'start',
                    // solid swatches in the line color, not the pale area fill
                    labels: { generateLabels: (chart) => Chart.defaults.plugins.legend.labels.generateLabels(chart)
                        .map((label) => ({ ...label, fillStyle: label.strokeStyle })) },
                },
                tooltip: { callbacks: { label: (item) => ` ${item.dataset.label}: ${dollars(item.parsed.y)}` } },
            },
        },
    });

    const growth = data['01_monthly_growth'];
    make('signups-chart', {
        type: 'bar',
        data: {
            labels: growth.map((row) => shortMonth(row.month)),
            datasets: [{ label: 'New customers', data: growth.map((row) => row.new_customers), backgroundColor: css('--series-1'),
                         maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' }],
        },
        options: {
            scales: { x: { ...noGrid, ticks: { maxTicksLimit: 8 } }, y: { ...axis, beginAtZero: true, ticks: { maxTicksLimit: 6 } } },
            plugins: { legend: { display: false } },
        },
    });
}

fetch('data.json')
    .then((response) => {
        if (!response.ok) throw new Error(`data.json: HTTP ${response.status}`);
        return response.json();
    })
    .then((data) => {
        showKpis(data);
        addTables(data);
        drawCharts(data);
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => drawCharts(data));
    })
    .catch((error) => {
        console.error(error);
        document.querySelector('.load-error').hidden = false;
    });
