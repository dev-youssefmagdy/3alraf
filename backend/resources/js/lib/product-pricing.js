/** Product form pricing maths: price = cost × (100 + profit%) / 100. Returns null when the inputs cannot give a result. */
const num = (v) => (v === '' || v === null || v === undefined ? NaN : Number(v));

export function priceFromProfit(cost, profit) {
    const c = num(cost), p = num(profit);
    if (!(c > 0) || !Number.isFinite(p) || p < -100) return null;
    return (Math.round((Math.round(c * 100) * (100 + p)) / 100) / 100).toFixed(2); // whole cents first: avoids float drift
}

export function profitFromPrice(cost, price) {
    const c = num(cost), v = num(price);
    if (!(c > 0) || !Number.isFinite(v)) return null;
    return String(Math.round(((v - c) / c) * 10000) / 100);
}
