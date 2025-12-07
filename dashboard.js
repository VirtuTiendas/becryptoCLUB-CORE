const state = {
    coins: ['BTC', 'ETH', 'KAS', 'CLORE', 'ALEO'],
    marketData: {},
    charts: new Map(),
    priceHistory: new Map(),
    themes: ['theme-neon', 'theme-vapor', 'theme-frost'],
    themeIndex: 0,
    aiLog: [],
    decisionLog: [],
    miners: [],
    pools: [
        { name: 'BeCrypto Pool • North', url: 'stratum+tcp://pool.becrypto.club:3333', region: 'Reykjavik' },
        { name: 'Hive Nexus • West', url: 'stratum+tcp://hive.nexus.ai:4444', region: 'Montreal' },
        { name: 'FluxGrid • Asia', url: 'stratum+tcp://fluxgrid.io:5555', region: 'Singapore' }
    ]
};

const selectors = {
    coinGrid: document.getElementById('coinGrid'),
    apiStatus: document.getElementById('apiStatus'),
    themeToggle: document.getElementById('themeToggle'),
    addCoinForm: document.getElementById('addCoinForm'),
    addCoinInput: document.getElementById('addCoinInput'),
    coinSearch: document.getElementById('coinSearch'),
    calculatorForm: document.getElementById('calculatorForm'),
    hashrateValue: document.getElementById('hashrateValue'),
    hashrateUnit: document.getElementById('hashrateUnit'),
    powerConsumption: document.getElementById('powerConsumption'),
    electricityCost: document.getElementById('electricityCost'),
    coinRevenue: document.getElementById('coinRevenue'),
    dailyProfit: document.getElementById('dailyProfit'),
    weeklyProfit: document.getElementById('weeklyProfit'),
    monthlyProfit: document.getElementById('monthlyProfit'),
    yearlyProfit: document.getElementById('yearlyProfit'),
    minersList: document.getElementById('minersList'),
    refreshMiners: document.getElementById('refreshMiners'),
    poolGrid: document.getElementById('poolGrid'),
    testPools: document.getElementById('testPools'),
    aiStateIndicator: document.getElementById('aiStateIndicator'),
    aiMetrics: document.getElementById('aiMetrics'),
    aiLog: document.getElementById('aiLog'),
    decisionLog: document.getElementById('decisionLog'),
    triggerAIAction: document.getElementById('triggerAIAction'),
    themeSwatches: document.querySelectorAll('.swatch')
};

/**
 * Fetch market prices from backend.
 */
async function fetchMarketPrices() {
    const symbolsParam = state.coins.join(',');
    const endpoint = `market_data.php?symbols=${encodeURIComponent(symbolsParam)}&fiats=USD&_=${Date.now()}`;
    updateApiStatus('loading');
    try {
        const response = await fetch(endpoint, { cache: 'no-store' });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        state.marketData = data;
        updatePricesUI();
        updateApiStatus('online');
    } catch (error) {
        console.error('Market fetch failed', error);
        updateApiStatus('offline');
        if (Object.keys(state.marketData).length === 0) {
            // Initialize placeholders so UI has content
            state.marketData = {
                prices: Object.fromEntries(state.coins.map((symbol) => [symbol, {
                    USD: 'N/A',
                    name: symbol,
                    rank: null,
                    src: 'offline-cache',
                    timestamp: new Date().toISOString(),
                    timestamp_unix: Math.floor(Date.now() / 1000),
                    change_24h: null
                }])),
                source: 'offline',
                fiat: 'USD',
                timestamp: new Date().toISOString(),
                timestamp_unix: Math.floor(Date.now() / 1000)
            };
        }
        updatePricesUI();
    }
}

/**
 * Update API status badge.
 */
function updateApiStatus(stateText) {
    const badge = selectors.apiStatus.querySelector('.badge');
    badge.textContent = stateText === 'online' ? 'Online' : stateText === 'loading' ? 'Loading' : 'Offline';
    badge.className = 'badge ' + (stateText === 'online' ? 'badge-on' : stateText === 'loading' ? 'badge-warn' : 'badge-off');
}

/**
 * Format price string.
 */
function formatPrice(value) {
    if (value === 'N/A' || value === null || value === undefined || Number.isNaN(value)) {
        return 'N/A';
    }
    const price = typeof value === 'number' ? value : parseFloat(value);
    if (!Number.isFinite(price)) {
        return 'N/A';
    }
    if (price >= 100) {
        return `$${price.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
    }
    if (price >= 1) {
        return `$${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
    return `$${price.toLocaleString(undefined, { minimumFractionDigits: 4, maximumFractionDigits: 8 })}`;
}

/**
 * Format percentage.
 */
function formatPercentage(value) {
    if (value === null || value === undefined || value === 'N/A') {
        return 'N/A';
    }
    const num = typeof value === 'number' ? value : parseFloat(value);
    if (!Number.isFinite(num)) {
        return 'N/A';
    }
    const formatted = num.toFixed(2);
    return `${num >= 0 ? '+' : ''}${formatted}%`;
}

/**
 * Build coin cards and charts.
 */
function updatePricesUI() {
    const grid = selectors.coinGrid;
    const prices = state.marketData.prices || {};
    const searchTerm = selectors.coinSearch.value?.trim().toUpperCase() || '';

    grid.innerHTML = '';

    state.coins.forEach((symbol) => {
        const data = prices[symbol] || {
            USD: 'N/A',
            name: symbol,
            rank: null,
            src: 'unknown',
            timestamp: new Date().toISOString(),
            timestamp_unix: Math.floor(Date.now() / 1000),
            change_24h: null
        };

        if (searchTerm && !symbol.includes(searchTerm) && !(data.name || '').toUpperCase().includes(searchTerm)) {
            return;
        }

        const card = document.createElement('article');
        card.className = 'coin-card neon-glow';
        card.dataset.symbol = symbol;

        const header = document.createElement('div');
        header.className = 'coin-header';
        const title = document.createElement('h3');
        title.textContent = `${data.name || symbol} (${symbol})`;
        const removeButton = document.createElement('button');
        removeButton.className = 'btn icon-only remove';
        removeButton.setAttribute('aria-label', `Remove ${symbol}`);
        removeButton.innerHTML = '×';
        removeButton.addEventListener('click', () => removeCoin(symbol));
        header.appendChild(title);
        header.appendChild(removeButton);

        const priceText = document.createElement('div');
        priceText.className = 'coin-price';
        priceText.textContent = formatPrice(data.USD);

        const meta = document.createElement('div');
        meta.className = 'coin-meta';
        const change = formatPercentage(data.change_24h);
        const changeClass = change.startsWith('+') ? 'pos' : change.startsWith('-') ? 'neg' : 'neu';
        meta.innerHTML = `<span class="change ${changeClass}">${change}</span><span class="source">${data.src}</span>`;

        const chartCanvas = document.createElement('canvas');
        chartCanvas.width = 220;
        chartCanvas.height = 80;
        chartCanvas.id = `chart-${symbol}`;

        card.appendChild(header);
        card.appendChild(priceText);
        card.appendChild(meta);
        card.appendChild(chartCanvas);
        grid.appendChild(card);

        updateSparkline(symbol, data.USD);
    });
}

/**
 * Update sparkline data for a coin.
 */
function updateSparkline(symbol, priceValue) {
    const canvas = document.getElementById(`chart-${symbol}`);
    if (!canvas) return;

    const numericPrice = typeof priceValue === 'number' ? priceValue : parseFloat(priceValue);
    const history = state.priceHistory.get(symbol) || [];

    if (Number.isFinite(numericPrice)) {
        history.push(numericPrice);
    } else if (history.length === 0) {
        history.push(0);
    } else {
        history.push(history[history.length - 1]);
    }

    if (history.length > 30) {
        history.splice(0, history.length - 30);
    }

    state.priceHistory.set(symbol, history);

    const chartKey = symbol;
    let chart = state.charts.get(chartKey);
    const theme = document.documentElement.className;
    const lineColor = theme.includes('vapor') ? 'rgba(255, 0, 123, 0.8)' : theme.includes('frost') ? 'rgba(0, 225, 255, 0.8)' : 'rgba(0, 173, 255, 0.85)';
    const gradient = canvas.getContext('2d').createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, lineColor.replace('0.8', '0.4'));
    gradient.addColorStop(1, 'rgba(0,0,0,0)');

    if (!chart) {
        chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: history.map((_, index) => index + 1),
                datasets: [{
                    data: history,
                    fill: true,
                    backgroundColor: gradient,
                    borderColor: lineColor,
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.35
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                scales: { x: { display: false }, y: { display: false } },
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });
        state.charts.set(chartKey, chart);
    } else {
        chart.data.labels = history.map((_, index) => index + 1);
        chart.data.datasets[0].data = history;
        chart.data.datasets[0].backgroundColor = gradient;
        chart.data.datasets[0].borderColor = lineColor;
        chart.update();
    }
}

/**
 * Remove coin from dashboard.
 */
function removeCoin(symbol) {
    if (state.coins.length <= 1) {
        alert('At least one coin must remain.');
        return;
    }
    state.coins = state.coins.filter((item) => item !== symbol);
    state.charts.delete(symbol);
    state.priceHistory.delete(symbol);
    fetchMarketPrices();
}

/**
 * Add coin to dashboard.
 */
function addCoin(symbol) {
    const upper = symbol.trim().toUpperCase();
    if (!upper) return;
    if (state.coins.includes(upper)) {
        selectors.coinSearch.value = '';
        updatePricesUI();
        return;
    }
    state.coins.push(upper);
    fetchMarketPrices();
}

/**
 * Initialize miners dataset with simulated values.
 */
function initializeMiners() {
    if (state.miners.length === 0) {
        state.miners = [
            { name: 'Rig-Alpha', model: 'AntMiner S21', location: 'Iceland', status: 'active', hashRate: 112, unit: 'TH/s', temp: 68 },
            { name: 'Rig-Beta', model: 'WhatsMiner M50', location: 'Canada', status: 'active', hashRate: 102, unit: 'TH/s', temp: 71 },
            { name: 'Rig-Gamma', model: 'GPU Array X12', location: 'Norway', status: 'standby', hashRate: 18, unit: 'GH/s', temp: 46 }
        ];
    }
    renderMiners();
}

/**
 * Render miners cards.
 */
function renderMiners() {
    selectors.minersList.innerHTML = '';
    state.miners.forEach((miner) => {
        const card = document.createElement('div');
        card.className = `miner-card status-${miner.status}`;
        card.innerHTML = `
            <header>
                <h3>${miner.name}</h3>
                <span>${miner.model}</span>
            </header>
            <div class="miner-meta">
                <span>${miner.hashRate} ${miner.unit}</span>
                <span>${miner.location}</span>
                <span>${miner.temp}°C</span>
            </div>
            <div class="status-indicator">${miner.status.toUpperCase()}</div>
        `;
        selectors.minersList.appendChild(card);
    });
}

/**
 * Randomize miner stats to simulate refresh.
 */
function refreshMiners() {
    state.miners = state.miners.map((miner) => {
        const variance = miner.unit.includes('TH') ? 2 : 0.5;
        const delta = (Math.random() - 0.5) * variance;
        const tempDelta = (Math.random() - 0.5) * 4;
        const status = Math.random() > 0.95 ? 'alert' : Math.random() > 0.1 ? 'active' : 'standby';
        return {
            ...miner,
            hashRate: Math.max(0, (miner.hashRate + delta)).toFixed(2),
            temp: Math.round(miner.temp + tempDelta),
            status
        };
    });
    renderMiners();
}

/**
 * Render mining pool cards.
 */
function renderPools() {
    selectors.poolGrid.innerHTML = '';
    state.pools.forEach((pool) => {
        const card = document.createElement('div');
        card.className = 'pool-card neon-glow';
        card.dataset.pool = pool.name;
        card.innerHTML = `
            <h3>${pool.name}</h3>
            <p>${pool.url}</p>
            <span class="region">${pool.region}</span>
            <div class="pool-status" data-status>Idle</div>
            <div class="hashrate" data-hashrate>—</div>
        `;
        selectors.poolGrid.appendChild(card);
    });
}

/**
 * Simulate asynchronous pool latency + hashrate.
 */
function testPoolConnectivity() {
    selectors.poolGrid.querySelectorAll('.pool-card').forEach((card) => {
        const statusEl = card.querySelector('[data-status]');
        const hashrateEl = card.querySelector('[data-hashrate]');
        statusEl.textContent = 'Testing…';
        statusEl.className = 'pool-status testing';
        hashrateEl.textContent = '—';

        const latency = Math.floor(Math.random() * 1200) + 120;
        setTimeout(() => {
            const success = Math.random() > 0.1;
            if (success) {
                const hash = (Math.random() * 3 + 0.5).toFixed(2);
                statusEl.textContent = `Online • ${latency} ms`;
                statusEl.className = 'pool-status online';
                hashrateEl.textContent = `${hash} PH/s`; // Pools show aggregated power
                hashrateEl.className = 'hashrate pulse';
            } else {
                statusEl.textContent = 'Offline';
                statusEl.className = 'pool-status offline';
                hashrateEl.textContent = '0 PH/s';
                hashrateEl.className = 'hashrate';
            }
        }, latency);
    });
}

/**
 * Handle profit calculation.
 */
function handleProfitCalculation(event) {
    event.preventDefault();
    const hashrateValue = parseFloat(selectors.hashrateValue.value || '0');
    const hashrateUnit = parseFloat(selectors.hashrateUnit.value || '1');
    const power = parseFloat(selectors.powerConsumption.value || '0');
    const cost = parseFloat(selectors.electricityCost.value || '0');
    const revenuePerTh = parseFloat(selectors.coinRevenue.value || '0');

    if ([hashrateValue, hashrateUnit, power, cost, revenuePerTh].some((value) => Number.isNaN(value))) {
        return;
    }

    const hashrateInTh = (hashrateValue * hashrateUnit) / 1e12;
    const grossDaily = hashrateInTh * revenuePerTh;
    const energyDaily = (power * 24) / 1000 * cost;
    const netDaily = grossDaily - energyDaily;

    updateProfitDisplay(netDaily);
}

/**
 * Update calculator outputs.
 */
function updateProfitDisplay(daily) {
    const weekly = daily * 7;
    const monthly = daily * 30;
    const yearly = daily * 365;
    selectors.dailyProfit.textContent = formatCurrency(daily);
    selectors.weeklyProfit.textContent = formatCurrency(weekly);
    selectors.monthlyProfit.textContent = formatCurrency(monthly);
    selectors.yearlyProfit.textContent = formatCurrency(yearly);
}

function formatCurrency(value) {
    const amount = Number.isFinite(value) ? value : 0;
    return `$${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/**
 * AI Control Center logic.
 */
function initializeAI() {
    updateAIMetrics();
    appendAILog('AI core boot sequence engaged. Synchronizing neural layers…');
    scheduleAIDecision();
}

function scheduleAIDecision() {
    setTimeout(() => {
        pushAIDecision();
        scheduleAIDecision();
    }, 30000);
}

function pushAIDecision() {
    const decision = generateAIDecision();
    state.decisionLog.unshift(decision.summary);
    state.decisionLog = state.decisionLog.slice(0, 12);
    renderDecisionLog();
    appendAILog(decision.detail);
    updateAIMetrics();
}

function generateAIDecision() {
    const actions = [
        'Rebalanced GPU allocation towards Kaspa',
        'Increased fan curve for Rig-Gamma',
        'Deployed predictive cooling algorithm',
        'Shifted mining pool priority to FluxGrid',
        'Activated profit shield hedging',
        'Executed auto-liquidation of idle rewards'
    ];
    const justifications = [
        'temperature drift detected',
        'market volatility spike',
        'hashrate efficiency gain',
        'AI risk model update',
        'neural governor anomaly correction',
        'power cost arbitrage opportunity'
    ];
    const action = actions[Math.floor(Math.random() * actions.length)];
    const cause = justifications[Math.floor(Math.random() * justifications.length)];
    const confidence = (Math.random() * 30 + 65).toFixed(1);
    return {
        summary: `${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} • ${action}`,
        detail: `${action} due to ${cause}. Confidence: ${confidence}%`
    };
}

function appendAILog(message) {
    const entry = document.createElement('div');
    entry.className = 'ai-log-entry';
    entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
    selectors.aiLog.prepend(entry);
    state.aiLog.unshift(message);
    if (selectors.aiLog.childElementCount > 30) {
        selectors.aiLog.removeChild(selectors.aiLog.lastElementChild);
    }
}

function renderDecisionLog() {
    selectors.decisionLog.innerHTML = '';
    state.decisionLog.forEach((item) => {
        const li = document.createElement('li');
        li.textContent = item;
        selectors.decisionLog.appendChild(li);
    });
}

function updateAIMetrics() {
    const metrics = [
        { label: 'Neural Load', value: `${(Math.random() * 35 + 60).toFixed(1)}%` },
        { label: 'Predictive Accuracy', value: `${(Math.random() * 6 + 92).toFixed(1)}%` },
        { label: 'Thermal Headroom', value: `${(Math.random() * 12 + 18).toFixed(1)}°C` }
    ];
    selectors.aiMetrics.innerHTML = '';
    metrics.forEach((metric) => {
        const li = document.createElement('li');
        li.innerHTML = `<span>${metric.label}</span><strong>${metric.value}</strong>`;
        selectors.aiMetrics.appendChild(li);
    });
    const states = ['Synchronizing', 'Optimizing', 'Coordinating', 'Predicting'];
    const stateText = states[Math.floor(Math.random() * states.length)];
    selectors.aiStateIndicator.textContent = `${stateText}…`;
}

/**
 * Theme handling.
 */
function applyTheme(themeName) {
    document.documentElement.className = themeName;
    state.themeIndex = state.themes.indexOf(themeName);
    if (state.themeIndex === -1) {
        state.themeIndex = 0;
    }
    localStorage.setItem('becrypto-theme', themeName);
}

function cycleTheme() {
    state.themeIndex = (state.themeIndex + 1) % state.themes.length;
    applyTheme(state.themes[state.themeIndex]);
}

function loadTheme() {
    const stored = localStorage.getItem('becrypto-theme');
    if (stored && state.themes.includes(stored)) {
        applyTheme(stored);
    } else {
        applyTheme(state.themes[0]);
    }
}

function handleSwatchClick(event) {
    const theme = event.currentTarget.dataset.theme;
    if (theme) {
        applyTheme(theme);
    }
}

/**
 * Initialize event listeners.
 */
function initEvents() {
    selectors.themeToggle.addEventListener('click', cycleTheme);
    selectors.addCoinForm.addEventListener('submit', (event) => {
        event.preventDefault();
        addCoin(selectors.addCoinInput.value);
        selectors.addCoinInput.value = '';
    });
    selectors.coinSearch.addEventListener('input', updatePricesUI);
    selectors.calculatorForm.addEventListener('submit', handleProfitCalculation);
    selectors.refreshMiners.addEventListener('click', () => {
        refreshMiners();
        appendAILog('Manual miner sync executed by operator.');
    });
    selectors.testPools.addEventListener('click', () => {
        testPoolConnectivity();
        appendAILog('Mining pool connectivity sweep initiated.');
    });
    selectors.triggerAIAction.addEventListener('click', () => {
        pushAIDecision();
    });
    selectors.themeSwatches.forEach((swatch) => swatch.addEventListener('click', handleSwatchClick));
}

function autoRefreshLoop() {
    fetchMarketPrices();
    setInterval(fetchMarketPrices, 60000);
}

function initFooterYear() {
    const yearElement = document.getElementById('year');
    if (yearElement) {
        yearElement.textContent = new Date().getFullYear();
    }
}

// Bootstrap dashboard
loadTheme();
initEvents();
initializeMiners();
renderPools();
initializeAI();
autoRefreshLoop();
initFooterYear();
