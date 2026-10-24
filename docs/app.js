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

// Tile map layout: [row, column] for each state on a 12 x 8 grid.
const STATE_TILES = {
    AK: [0, 0], ME: [0, 11], VT: [1, 10], NH: [1, 11],
    WA: [2, 1], ID: [2, 2], MT: [2, 3], ND: [2, 4], MN: [2, 5], IL: [2, 6], WI: [2, 7], MI: [2, 8], NY: [2, 9], RI: [2, 10], MA: [2, 11],
    OR: [3, 1], NV: [3, 2], WY: [3, 3], SD: [3, 4], IA: [3, 5], IN: [3, 6], OH: [3, 7], PA: [3, 8], NJ: [3, 9], CT: [3, 10],
    CA: [4, 1], UT: [4, 2], CO: [4, 3], NE: [4, 4], MO: [4, 5], KY: [4, 6], WV: [4, 7], VA: [4, 8], MD: [4, 9], DE: [4, 10],
    AZ: [5, 2], NM: [5, 3], KS: [5, 4], AR: [5, 5], TN: [5, 6], NC: [5, 7], SC: [5, 8], DC: [5, 9],
    OK: [6, 4], LA: [6, 5], MS: [6, 6], AL: [6, 7], GA: [6, 8],
    HI: [7, 0], TX: [7, 4], FL: [7, 9],
};

// Which of five bins a value falls in, given four ascending thresholds.
const binOf = (value, thresholds) => 1 + thresholds.filter((t) => value >= t).length;

function binLegend(listId, labels) {
    document.getElementById(listId).innerHTML =
        labels.map((label, i) => `<li><i class="bin-${i + 1}"></i>${label}</li>`).join('');
}

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
    fill('top_decile_share', `${Math.round(data['03_deposit_concentration'][0].share_pct)}%`);
    fill('saturday_wait', `${Math.round(data['06_review_turnaround'].find((row) => row.day_sent === 'Sat').median_hours)} hours`);
}

// The map and heatmap are plain HTML colored by CSS classes, so they follow light/dark mode on their own.
function drawStateMap(data) {
    const thresholds = [10, 25, 50, 100];
    document.getElementById('state-map').innerHTML = data.customers_by_state.map(({ state, customers, money_held }) => {
        const [row, column] = STATE_TILES[state];
        const label = `${state}: ${customers} customers, ${money(money_held)} held`;
        return `<div class="tile bin-${binOf(customers, thresholds)}" style="grid-row:${row + 1};grid-column:${column + 1}"
                     title="${label}" aria-label="${label}">${state}<small>${customers}</small></div>`;
    }).join('');
    binLegend('state-legend', ['Under 10', '10-24', '25-49', '50-99', '100 or more']);
}

function drawRetention(data) {
    const thresholds = [0.6, 0.7, 0.8, 0.9];
    const rows = data.cohort_retention;
    const cohorts = [...new Set(rows.map((row) => row.cohort))];
    const months = Math.max(...rows.map((row) => row.months_since_signup)) + 1;
    const lookup = new Map(rows.map((row) => [`${row.cohort}|${row.months_since_signup}`, row]));
    const header = Array.from({ length: months }, (_, m) => `<th scope="col">${m % 3 === 0 ? m : ''}</th>`).join('');
    const body = cohorts.map((cohort) => {
        const cells = Array.from({ length: months }, (_, m) => {
            const row = lookup.get(`${cohort}|${m}`);
            if (!row) return '<td></td>';
            const label = `${shortMonth(cohort)} signups, month ${m}: ${percent(row.retention)} active (${row.active_customers} of ${row.cohort_size})`;
            return `<td class="bin-${binOf(row.retention, thresholds)}" title="${label}" aria-label="${label}"></td>`;
        }).join('');
        return `<tr><th scope="row">${shortMonth(cohort)}</th>${cells}</tr>`;
    }).join('');
    document.getElementById('retention-heatmap').innerHTML =
        `<thead><tr><th scope="col">Months after signup</th>${header}</tr></thead><tbody>${body}</tbody>`;
    binLegend('retention-legend', ['Under 60% active', '60-69%', '70-79%', '80-89%', '90% or more']);
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
    addTable('states-figure', ['State', 'Customers', 'Money held'],
        [...data.customers_by_state].sort((a, b) => b.customers - a.customers)
            .map((row) => [row.state, row.customers, dollars(row.money_held)]));
    addTable('deciles-figure', ['Tenth of customers', 'Customers', 'Money held', 'Share', 'Running share'],
        data['03_deposit_concentration'].map((row) => [decileName(row.decile), row.customers, dollars(row.money_held),
            `${row.share_pct}%`, `${row.cumulative_share_pct}%`]));
    addTable('retention-figure', ['Signup month', 'Months later', 'Active', 'Share active'],
        data.cohort_retention.map((row) => [shortMonth(row.cohort), row.months_since_signup,
            `${row.active_customers} of ${row.cohort_size}`, percent(row.retention)]));
    addTable('wait-figure', ['Day sent', 'Checks', 'Median hours', '90th percentile hours', 'Waited over 24 hours'],
        data['06_review_turnaround'].map((row) => [row.day_sent, count(row.checks), row.median_hours, row.p90_hours, `${row.over_24h_pct}%`]));
    addTable('reviewers-figure', ['Employee', 'Role', 'Reviewed', 'Denied', 'Denial rate', 'z-score vs team'],
        data['08_reviewer_denials'].map((row) => [row.employee_id, roleName(row.reviewer_tier), count(row.reviewed), row.denied,
            `${row.denial_pct}%`, row.z_score]));
    addTable('sizes-figure', ['Check size', 'Checks', 'Denied', 'Denial rate', 'Denied dollars'],
        data['07_denials_by_check_size'].map((row) => [row.check_size, count(row.checks), row.denied, `${row.denial_pct}%`, dollars(row.denied_amount)]));
}

const roleName = (tier) => ['', 'Teller', 'Manager', 'Admin'][tier];

// Draws a labeled vertical reference line (e.g. the team average) across a chart.
const referenceLine = {
    id: 'reference',
    afterDatasetsDraw(chart, _args, { value, label, color }) {
        const { ctx, chartArea, scales } = chart;
        const x = scales.x.getPixelForValue(value);
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
        ctx.stroke();
        ctx.fillStyle = color;
        ctx.font = `12px ${Chart.defaults.font.family}`;
        ctx.textAlign = 'left';
        ctx.fillText(label, x + 6, chartArea.bottom - 6);
        ctx.restore();
    },
};

const decileName = (decile) => (decile === 1 ? 'Top 10%' : decile === 10 ? 'Bottom 10%' : `${(decile - 1) * 10}-${decile * 10}%`);

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

    const deciles = data['03_deposit_concentration'];
    make('deciles-chart', {
        type: 'bar',
        data: {
            labels: deciles.map((row) => row.decile),
            datasets: [{ label: 'Share of money', data: deciles.map((row) => row.share_pct), backgroundColor: css('--series-1'),
                         maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' }],
        },
        options: {
            scales: {
                x: { ...noGrid, title: { display: true, text: 'Tenth of customers, from richest (1) to poorest (10)' } },
                y: { ...axis, beginAtZero: true, ticks: { callback: (v) => `${v}%`, maxTicksLimit: 6 } },
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: {
                    title: (items) => decileName(deciles[items[0].dataIndex].decile),
                    label: (item) => ` ${item.parsed.y}% of all money (${dollars(deciles[item.dataIndex].money_held)})`,
                } },
            },
        },
    });

    // Weekday and weekend are separate series (with gaps) so the legend can name the orange bars.
    const waits = data['06_review_turnaround'];
    const isWeekend = (row) => row.day_number >= 6;
    const waitBars = (label, keep, color) => ({
        label, data: waits.map((row) => (keep(row) ? row.median_hours : null)), backgroundColor: color,
        maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start', skipNull: true,
    });
    make('wait-chart', {
        type: 'bar',
        data: {
            labels: waits.map((row) => row.day_sent),
            datasets: [waitBars('Weekday', (row) => !isWeekend(row), css('--series-1')), waitBars('Weekend', isWeekend, css('--series-2'))],
        },
        options: {
            scales: { x: noGrid, y: { ...axis, beginAtZero: true, ticks: { callback: (v) => `${v}h`, maxTicksLimit: 6 } } },
            plugins: {
                legend: { position: 'top', align: 'start' },
                tooltip: { callbacks: { label: (item) => {
                    const row = waits[item.dataIndex];
                    return [` Median ${row.median_hours} hours, 90th percentile ${row.p90_hours} hours`,
                            ` ${row.over_24h_pct}% waited over a day (${count(row.checks)} checks)`];
                } } },
            },
        },
    });

    const reviewers = [...data['08_reviewer_denials']].sort((a, b) => b.denial_pct - a.denial_pct);
    make('reviewers-chart', {
        type: 'bar',
        plugins: [referenceLine],
        data: {
            labels: reviewers.map((row) => `Employee ${row.employee_id} (${roleName(row.reviewer_tier).toLowerCase()})`),
            datasets: [{ label: 'Denial rate', data: reviewers.map((row) => row.denial_pct), backgroundColor: css('--series-1'),
                         maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' }],
        },
        options: {
            indexAxis: 'y',
            scales: { x: { ...axis, beginAtZero: true, ticks: { callback: (v) => `${v}%`, maxTicksLimit: 6 } }, y: noGrid },
            plugins: {
                legend: { display: false },
                reference: { value: reviewers[0].team_pct, label: `Team ${reviewers[0].team_pct.toFixed(1)}%`, color: css('--ink-2') },
                tooltip: { callbacks: { label: (item) => {
                    const row = reviewers[item.dataIndex];
                    return [` Denied ${row.denied} of ${count(row.reviewed)} checks (${row.denial_pct}%)`, ` z-score vs team: ${row.z_score}`];
                } } },
            },
        },
    });

    const sizes = data['07_denials_by_check_size'];
    make('sizes-chart', {
        type: 'bar',
        data: {
            labels: sizes.map((row) => row.check_size),
            datasets: [{ label: 'Denial rate', data: sizes.map((row) => row.denial_pct), backgroundColor: css('--series-1'),
                         maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' }],
        },
        options: {
            scales: { x: noGrid, y: { ...axis, beginAtZero: true, ticks: { callback: (v) => `${v}%`, maxTicksLimit: 6 } } },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (item) => {
                    const row = sizes[item.dataIndex];
                    return [` ${row.denial_pct}% denied (${row.denied} of ${count(row.checks)})`, ` ${dollars(row.denied_amount)} in denied checks`];
                } } },
            },
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
        drawStateMap(data);
        drawRetention(data);
        addTables(data);
        drawCharts(data);
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => drawCharts(data));
    })
    .catch((error) => {
        console.error(error);
        document.querySelector('.load-error').hidden = false;
    });
