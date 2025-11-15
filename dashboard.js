const DEFAULT_COINS = ['BTC', 'ETH', 'KAS', 'CLORE', 'ALEO'];
const STORAGE_KEYS = {
    coins: 'becrypto.coins',
    theme: 'becrypto.theme',
    miners: 'becrypto.miners',
};

const state = {
    coins: [],
    prices: {},
    charts: {},
    history: {},
    miners: [],
};

const THEME_CLASSES = ['theme-azure', 'theme-magenta', 'theme-moon'];
let themeIndex = 0;

const aiMessages = [
    'Optimizing GPU clusters for peak efficiency.',
    'Scanning MEXC orderbooks for arbitrage opportunities.',
    'Deploying predictive model to forecast hourly volatility.',
    'Balancing thermal loads across personal miners.',
    'Running Monte Carlo simulations on staking yields.',
    'Reinforcing anomaly detection thresholds for pools.',
    'Updating reinforcement learning policy weights.',
    'Reviewing historical decision log for pattern drift.',
];

const aiDecisions = [
    'Shift 20% of hashpower to KAS pools based on profitability spike.',
    'Pause ALEO mining pending network upgrade confirmation.',
    'Allocate idle GPUs to AI inference workloads for 12 hours.',
    'Increase ETH auto-sell threshold to protect against flash dips.',
    'Rebalance BTC holdings into cold storage vault.',
    'Deploy maintenance script to reflash miner firmware.',
    'Activate failover pool for CLORE due to latency.',
    'Boost cooling fan speeds by 15% to maintain 62°C target.',
];

const minersStatusTemplates = [
    { status: 'Online', load: 0.95 },
    { status: 'Optimizing', load: 0.78 },
    { status: 'Idle', load: 0.12 },
    { status: 'Maintenance', load: 0.0 },
];

const poolTestResults = [
    { status: 'Operational', latency: 48, hashrate: '1.54 PH/s' },
    { status: 'Degraded', latency: 134, hashrate: '0.92 PH/s' },
    { status: 'Failover Active', latency: 88, hashrate: '1.02 PH/s' },
    { status: 'Offline', latency: null, hashrate: '0 PH/s' },
];

const BLOCKS_PER_DAY = 144;

/**
 * Initialize dashboard
 */
document.addEventListener('DOMContentLoaded', () => {
    initializeTheme();
    initializeCoins();
    initializeEventHandlers();
    renderMiners();
    fetchMarketPrices();
    scheduleAiLoop();
    setInterval(fetchMarketPrices, 60_000);
});

function initializeEventHandlers() {
    document.getElementById('themeToggle').addEventListener('click', cycleTheme);
    document.getElementById('fiatSelect').addEventListener('change', fetchMarketPrices);
    document.getElementById('coinSearch').addEventListener('input', filterCoins);
    document.getElementById('addCoinBtn').addEventListener('click', onAddCoin);
    document.getElementById('profitForm').addEventListener('submit', onProfitCalculate);
    document.getElementById('addMinerBtn').addEventListener('click', onAddMiner);
    document.getElementById('testPoolBtn').addEventListener('click', onTestPool);
}

function initializeCoins() {
    const stored = localStorage.getItem(STORAGE_KEYS.coins);
    if (stored) {
        try {
            const parsed = JSON.parse(stored);
            if (Array.isArray(parsed) && parsed.length > 0) {
                state.coins = Array.from(new Set(parsed.map((s) => s.toUpperCase())));
            }
        } catch (error) {
            console.error('Failed to parse stored coins', error);
        }
    }

    if (state.coins.length === 0) {
        state.coins = [...DEFAULT_COINS];
    }
}

function initializeTheme() {
    const storedTheme = localStorage.getItem(STORAGE_KEYS.theme);
    if (storedTheme && THEME_CLASSES.includes(storedTheme)) {
        themeIndex = THEME_CLASSES.indexOf(storedTheme);
        applyThemeClass(storedTheme);
    } else {
        applyThemeClass(THEME_CLASSES[themeIndex]);
    }
}

function scheduleAiLoop() {
    generateAiConsoleMessage();
    generateAiDecision();
    setInterval(() => {
        generateAiConsoleMessage();
        generateAiDecision();
    }, 30_000);
}

/**
 * Fetch market prices from backend API
 */
async function fetchMarketPrices() {
    if (state.coins.length === 0) {
        return;
    }
    const fiat = document.getElementById('fiatSelect').value || 'USD';
    const url = `market_data.php?symbols=${encodeURIComponent(state.coins.join(','))}&fiats=${encodeURIComponent(fiat)}`;

    try {
        const response = await fetch(url, { cache: 'no-store' });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        updatePricesState(payload);
        updatePricesUI();
        updateLastUpdated(payload.timestamp);
    } catch (error) {
        console.error('Failed to fetch prices', error);
        updateLastUpdated('Error');
        displayErrorStates();
    }
}

function updateLastUpdated(timestamp) {
    const el = document.getElementById('lastUpdated');
    el.textContent = timestamp ? new Date(timestamp).toLocaleString() : '--';
}

function updatePricesState(payload) {
    if (!payload || typeof payload !== 'object') {
        return;
    }
    const fiat = payload.fiat || 'USD';
    const prices = payload.prices || {};

    Object.entries(prices).forEach(([symbol, data]) => {
        if (!state.history[symbol]) {
            state.history[symbol] = [];
        }
        const priceValue = typeof data[fiat] === 'number' ? data[fiat] : null;
        if (priceValue !== null) {
            state.history[symbol].push({
                time: Date.now(),
                price: priceValue,
            });
            if (state.history[symbol].length > 120) {
                state.history[symbol] = state.history[symbol].slice(-120);
            }
        }
    });

    state.prices = prices;
}

function displayErrorStates() {
    const container = document.getElementById('coinsContainer');
    container.innerHTML = '';
    const warning = document.createElement('div');
    warning.className = 'error-message';
    warning.textContent = 'Unable to load market data. Retrying...';
    container.appendChild(warning);
}

/**
 * Update coin cards UI
 */
function updatePricesUI() {
    const container = document.getElementById('coinsContainer');
    container.innerHTML = '';
    const fiat = document.getElementById('fiatSelect').value || 'USD';
    const search = document.getElementById('coinSearch').value.trim().toUpperCase();

    state.coins
        .filter((symbol) => symbol.includes(search))
        .forEach((symbol) => {
            const data = state.prices[symbol] || {};
            const price = typeof data[fiat] === 'number' ? data[fiat] : null;
            const change = calculateChange(symbol, price);
            const card = document.createElement('div');
            card.className = 'coin-card neon-border';
            card.dataset.symbol = symbol;

            const header = document.createElement('div');
            header.className = 'coin-card-header';
            header.innerHTML = `
                <div>
                    <h3>${symbol}</h3>
                    <span class="coin-name">${data.name || symbol}</span>
                </div>
                <button class="remove-coin" data-symbol="${symbol}" title="Remove">×</button>
            `;

            const body = document.createElement('div');
            body.className = 'coin-card-body';
            body.innerHTML = `
                <div class="price">${formatPrice(price)}</div>
                <div class="change ${change >= 0 ? 'positive' : 'negative'}">${formatChange(change)}</div>
            `;

            const canvas = document.createElement('canvas');
            canvas.id = `spark-${symbol}`;
            canvas.width = 160;
            canvas.height = 60;

            card.appendChild(header);
            card.appendChild(body);
            card.appendChild(canvas);

            container.appendChild(card);

            header.querySelector('.remove-coin').addEventListener('click', () => removeCoin(symbol));
            renderSparkline(symbol, canvas);
        });
}

function formatPrice(price) {
    if (price === null) {
        return 'N/A';
    }
    if (price >= 1000) {
        return `$${price.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
    }
    return `$${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 })}`;
}

function calculateChange(symbol, currentPrice) {
    const history = state.history[symbol] || [];
    if (!currentPrice || history.length < 2) {
        return 0;
    }
    const previous = history[history.length - 2]?.price;
    if (!previous) {
        return 0;
    }
    return ((currentPrice - previous) / previous) * 100;
}

function formatChange(value) {
    const formatted = value >= 0 ? `+${value.toFixed(2)}%` : `${value.toFixed(2)}%`;
    return formatted;
}

function renderSparkline(symbol, canvas) {
    const history = state.history[symbol] || [];
    if (!canvas) {
        return;
    }

    const ctx = canvas.getContext('2d');
    if (state.charts[symbol]) {
        state.charts[symbol].destroy();
    }

    const labels = history.map((entry) => new Date(entry.time).toLocaleTimeString());
    const data = history.map((entry) => entry.price);

    state.charts[symbol] = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data,
                    borderColor: 'rgba(0, 255, 255, 0.8)',
                    backgroundColor: 'rgba(0, 255, 255, 0.15)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 0,
                },
            ],
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            scales: {
                x: { display: false },
                y: { display: false },
            },
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
            },
        },
    });
}

function filterCoins() {
    updatePricesUI();
}

function onAddCoin() {
    const symbol = prompt('Enter coin symbol (e.g., BTC):');
    if (!symbol) {
        return;
    }
    const upper = symbol.trim().toUpperCase();
    if (!upper) {
        return;
    }
    if (state.coins.includes(upper)) {
        alert('Coin already added.');
        return;
    }
    state.coins.push(upper);
    persistCoins();
    fetchMarketPrices();
}

function removeCoin(symbol) {
    state.coins = state.coins.filter((s) => s !== symbol);
    persistCoins();
    updatePricesUI();
}

function persistCoins() {
    localStorage.setItem(STORAGE_KEYS.coins, JSON.stringify(state.coins));
}

function onProfitCalculate(event) {
    event.preventDefault();

    const hashrateValue = parseFloat(document.getElementById('hashrate').value);
    const hashrateUnit = document.getElementById('hashrateUnit').value;
    const power = parseFloat(document.getElementById('power').value);
    const cost = parseFloat(document.getElementById('cost').value);
    const reward = parseFloat(document.getElementById('reward').value);
    const networkHashrate = parseFloat(document.getElementById('networkHashrate').value);
    const coinPrice = parseFloat(document.getElementById('coinPrice').value);

    if ([hashrateValue, power, cost, reward, networkHashrate, coinPrice].some((val) => isNaN(val) || val < 0)) {
        alert('Please fill in valid positive values.');
        return;
    }

    const userHashrateHs = convertToHashrate(hashrateValue, hashrateUnit);
    const networkHashrateHs = networkHashrate * 1e15; // PH/s -> H/s

    if (networkHashrateHs === 0) {
        alert('Network hashrate must be greater than zero.');
        return;
    }

    const userShare = userHashrateHs / networkHashrateHs;
    const coinsPerDay = userShare * BLOCKS_PER_DAY * reward;
    const revenuePerDay = coinsPerDay * coinPrice;
    const energyCostPerDay = (power / 1000) * 24 * cost;
    const profitPerDay = revenuePerDay - energyCostPerDay;

    updateProfitResults(profitPerDay);
}

function convertToHashrate(value, unit) {
    const multipliers = {
        KH: 1e3,
        MH: 1e6,
        GH: 1e9,
        TH: 1e12,
    };
    return value * (multipliers[unit] || 1);
}

function updateProfitResults(profitPerDay) {
    const results = {
        profitDaily: profitPerDay,
        profitWeekly: profitPerDay * 7,
        profitMonthly: profitPerDay * 30,
        profitYearly: profitPerDay * 365,
    };

    Object.entries(results).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = formatCurrency(value);
        }
    });
}

function formatCurrency(value) {
    return `${value >= 0 ? '' : '-'}$${Math.abs(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function initializeMinersState() {
    if (state.miners) {
        return;
    }
    const stored = localStorage.getItem(STORAGE_KEYS.miners);
    if (stored) {
        try {
            state.miners = JSON.parse(stored);
        } catch (error) {
            console.error('Failed to parse miners', error);
        }
    }
    if (!Array.isArray(state.miners)) {
        state.miners = [
            { name: 'Rig-Alpha', hashrate: '120 MH/s', status: 'Online' },
            { name: 'Rig-Beta', hashrate: '96 MH/s', status: 'Optimizing' },
            { name: 'Rig-Gamma', hashrate: '1.2 GH/s', status: 'Idle' },
        ];
    }
}

function renderMiners() {
    initializeMinersState();
    const container = document.getElementById('minersList');
    container.innerHTML = '';
    state.miners.forEach((miner, index) => {
        const card = document.createElement('div');
        card.className = 'miner-card neon-border';
        card.innerHTML = `
            <div class="miner-header">
                <h3>${miner.name}</h3>
                <button class="remove-miner" data-index="${index}">×</button>
            </div>
            <div class="miner-body">
                <span class="tag">${miner.status}</span>
                <span>${miner.hashrate}</span>
            </div>
        `;
        card.querySelector('.remove-miner').addEventListener('click', () => removeMiner(index));
        container.appendChild(card);
    });
}

function onAddMiner() {
    const name = prompt('Miner name:');
    if (!name) {
        return;
    }
    const hashrate = prompt('Hashrate (e.g., 120 MH/s):');
    if (!hashrate) {
        return;
    }
    const template = minersStatusTemplates[Math.floor(Math.random() * minersStatusTemplates.length)];
    state.miners.push({
        name: name.trim(),
        hashrate: hashrate.trim(),
        status: template.status,
    });
    persistMiners();
    renderMiners();
}

function removeMiner(index) {
    state.miners.splice(index, 1);
    persistMiners();
    renderMiners();
}

function persistMiners() {
    localStorage.setItem(STORAGE_KEYS.miners, JSON.stringify(state.miners));
}

function onTestPool() {
    const indicator = document.getElementById('poolIndicator');
    const statusText = document.getElementById('poolStatusText');
    const hashrateText = document.getElementById('poolHashrate');

    indicator.classList.add('testing');
    statusText.textContent = 'Testing...';
    hashrateText.textContent = 'Calculating...';

    setTimeout(() => {
        const result = poolTestResults[Math.floor(Math.random() * poolTestResults.length)];
        indicator.classList.remove('testing');
        indicator.dataset.status = result.status;
        indicator.className = `status-indicator status-${result.status.replace(/\s+/g, '-').toLowerCase()}`;
        statusText.textContent = result.status;
        hashrateText.textContent = result.hashrate;
    }, 1800 + Math.random() * 1200);
}

function generateAiConsoleMessage() {
    const consoleEl = document.getElementById('aiConsole');
    if (!consoleEl) {
        return;
    }
    const message = aiMessages[Math.floor(Math.random() * aiMessages.length)];
    const entry = document.createElement('div');
    entry.className = 'ai-log-entry';
    entry.innerHTML = `<span class="timestamp">${new Date().toLocaleTimeString()}</span>${message}`;
    consoleEl.prepend(entry);
    trimChildNodes(consoleEl, 20);
}

function generateAiDecision() {
    const logEl = document.getElementById('decisionLog');
    if (!logEl) {
        return;
    }
    const decision = aiDecisions[Math.floor(Math.random() * aiDecisions.length)];
    const li = document.createElement('li');
    li.innerHTML = `
        <div class="decision-time">${new Date().toLocaleString()}</div>
        <div class="decision-text">${decision}</div>
    `;
    logEl.prepend(li);
    trimChildNodes(logEl, 30);
}

function trimChildNodes(parent, max) {
    while (parent.children.length > max) {
        parent.removeChild(parent.lastChild);
    }
}

function cycleTheme() {
    themeIndex = (themeIndex + 1) % THEME_CLASSES.length;
    const theme = THEME_CLASSES[themeIndex];
    applyThemeClass(theme);
    localStorage.setItem(STORAGE_KEYS.theme, theme);
}

function applyThemeClass(theme) {
    document.body.classList.remove(...THEME_CLASSES);
    document.body.classList.add(theme);
}
