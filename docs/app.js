// Fills the page from data.json, which build.py writes from the SQL analysis.

const money = (value) =>
    value >= 1e6 ? `$${(value / 1e6).toFixed(1)}M` : value >= 1e3 ? `$${Math.round(value / 1e3)}K` : `$${Math.round(value)}`;
const count = (value) => value.toLocaleString('en-US');
const percent = (share, digits = 0) => `${(share * 100).toFixed(digits)}%`;
const monthName = (isoDate) => new Date(`${isoDate.slice(0, 10)}T00:00`).toLocaleDateString('en-US', { month: 'long' });

function fill(name, text) {
    document.querySelectorAll(`[data-fill="${name}"]`).forEach((el) => { el.textContent = text; });
}

function showKpis(data) {
    const { totals } = data;
    const lastMonth = data['01_monthly_growth'].at(-1);
    const dormant = data['05_dormant_money'].find((row) => row.inactive_for === 'All dormant');

    fill('as_of', new Date(`${totals.as_of.slice(0, 10)}T00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
    fill('money_held', money(totals.money_held));
    fill('money_note', `Up ${lastMonth.money_growth_pct}% in ${monthName(lastMonth.month)}`);
    fill('customers', count(totals.customers));
    fill('customers_note', `${lastMonth.new_customers} joined in ${monthName(lastMonth.month)}`);
    fill('active_share', percent(totals.active_customers / totals.customers));
    fill('active_note', `${count(totals.active_customers)} moved money in the last 90 days`);
    fill('dormant_money', money(dormant.money_held));
    fill('dormant_note', `${dormant.share_of_all_money_pct}% of all money, with ${dormant.customers} customers`);
}

fetch('data.json')
    .then((response) => {
        if (!response.ok) throw new Error(`data.json: HTTP ${response.status}`);
        return response.json();
    })
    .then(showKpis)
    .catch((error) => {
        console.error(error);
        document.querySelector('.load-error').hidden = false;
    });
