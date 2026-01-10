const REVEAL_DELAY_MS = 0;
const SPIN_DURATION_MS = 4200;
const SPIN_MIN_LOOPS = 2;
const ROLL_SPEED_PX_PER_MS = 2.6;
const RESULT_PAUSE_MS = 10000;
let rouletteCleanup = null;

const initRoulette = () => {
    const roulettePage = document.querySelector('.roulette-page');
    if (!roulettePage) {
        return null;
    }

    const stateUrl = roulettePage.dataset.stateUrl;
    const wheelTrack = roulettePage.querySelector('[data-wheel-track]');
    const timerEl = roulettePage.querySelector('[data-timer]');
    const statusEl = roulettePage.querySelector('[data-status-text]');
    const historyEl = roulettePage.querySelector('[data-history]');
    const betTypeInput = roulettePage.querySelector('[data-bet-type-input]');
    const betValueInput = roulettePage.querySelector('[data-bet-value-input]');
    const amountInput = roulettePage.querySelector('[data-amount-input]');
    const selectedEl = roulettePage.querySelector('[data-selected-bet]');
    const amountPreviewEl = roulettePage.querySelector('.bet-amount-preview');
    const roundBetEl = roulettePage.querySelector('[data-round-bet]');
    const betForm = roulettePage.querySelector('.bet-form');
    const placeBetBtn = roulettePage.querySelector('.place-bet');

    let serverOffsetMs = 0;
    let currentRoundId = roulettePage.dataset.roundId;
    let currentEndsAt = new Date(roulettePage.dataset.roundEnds).getTime();
    let pendingResult = roulettePage.dataset.roundResult ?? '';
    let currentResult = '';
    let revealAtMs = pendingResult !== '' ? currentEndsAt + REVEAL_DELAY_MS : null;
    let isSettling = false;
    let rollingOffset = null;
    let rollingRafId = null;
    let rollingLastTs = null;
    let settleRafId = null;
    let loopCache = null;
    let historyBlockedUntil = 0;
    let settleEndsAt = 0;
    let holdUntil = 0;
    let queuedRound = null;
    let pendingBalance = null;
    const balanceEl = roulettePage.querySelector('.balance-card strong');

    const normalizeResult = (value) => (value === null || value === undefined ? '' : String(value));
    const hasPendingResult = () => pendingResult !== '';

    const updateOffset = (serverTime) => {
        const serverMs = new Date(serverTime).getTime();
        serverOffsetMs = serverMs - Date.now();
    };

    updateOffset(roulettePage.dataset.serverTime);
    if (pendingResult !== '' && revealAtMs !== null && Date.now() + serverOffsetMs >= revealAtMs) {
        currentResult = pendingResult;
        positionWheel(currentResult);
    }

    const setSelected = (type, value) => {
        if (!betTypeInput || !betValueInput || !selectedEl) {
            return;
        }

        betTypeInput.value = type;
        betValueInput.value = value;
        selectedEl.textContent = `${type}: ${value}`;

        roulettePage.querySelectorAll('.is-selected').forEach((el) => {
            el.classList.remove('is-selected');
        });
        const selector = `[data-bet-type="${type}"][data-bet-value="${value}"]`;
        const button = roulettePage.querySelector(selector);
        if (button) {
            button.classList.add('is-selected');
        }
        updateBetSummary();
    };

    const updateBetSummary = () => {
        if (!selectedEl) {
            return;
        }
        const amount = amountInput ? amountInput.value : '';
        const readableAmount = amount ? `${amount} zł` : '—';
        if (amountPreviewEl) {
            amountPreviewEl.textContent = `Stawka: ${readableAmount}`;
        }
        if (betTypeInput && betValueInput) {
            const type = betTypeInput.value || 'Brak';
            const value = betValueInput.value || '';
            selectedEl.textContent = value ? `${type} ${value}` : type;
        }
    };

    const roundBetKey = () => `roulette.round.${currentRoundId}.bet`;

    const updateRoundBet = (entry) => {
        if (!roundBetEl) {
            return;
        }
        if (!entry) {
            roundBetEl.textContent = 'Brak';
            return;
        }
        roundBetEl.textContent = `${entry.type} ${entry.value} | ${entry.amount} zł`;
    };

    const loadRoundBet = () => {
        const stored = sessionStorage.getItem(roundBetKey());
        if (!stored) {
            updateRoundBet(null);
            return;
        }
        try {
            updateRoundBet(JSON.parse(stored));
        } catch (error) {
            updateRoundBet(null);
        }
    };

    roulettePage.querySelectorAll('[data-bet-type]').forEach((button) => {
        button.addEventListener('click', () => {
            const type = button.dataset.betType;
            const value = button.dataset.betValue;
            setSelected(type, value);
        });
    });

    roulettePage.querySelectorAll('[data-amount-add]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!amountInput) {
                return;
            }
            const value = Number(button.dataset.amountAdd);
            const current = Number(amountInput.value || 0);
            amountInput.value = current + value;
            updateBetSummary();
        });
    });

    roulettePage.querySelectorAll('[data-amount-mul]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!amountInput) {
                return;
            }
            const value = Number(button.dataset.amountMul);
            const current = Number(amountInput.value || 0);
            amountInput.value = Math.max(0, Math.floor(current * value));
            updateBetSummary();
        });
    });

    const clearBtn = roulettePage.querySelector('[data-amount-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (!amountInput) {
                return;
            }
            amountInput.value = '';
            updateBetSummary();
        });
    }

    const maxBtn = roulettePage.querySelector('[data-amount-max]');
    if (maxBtn) {
        maxBtn.addEventListener('click', () => {
            if (!amountInput) {
                return;
            }
            const balanceEl = roulettePage.querySelector('.balance-card strong');
            if (!balanceEl) {
                return;
            }
            const parsed = Number(balanceEl.textContent.trim());
            amountInput.value = Number.isNaN(parsed) ? '' : parsed;
            updateBetSummary();
        });
    }

    if (betForm) {
        betForm.addEventListener('submit', () => {
            if (placeBetBtn && placeBetBtn.disabled) {
                return;
            }
            if (!betTypeInput || !betValueInput || !amountInput) {
                return;
            }
            const entry = {
                type: betTypeInput.value || 'Brak',
                value: betValueInput.value || '',
                amount: amountInput.value || '0',
            };
            sessionStorage.setItem(roundBetKey(), JSON.stringify(entry));
        });
    }

    const getCurrentOffset = () => {
        if (!wheelTrack) {
            return null;
        }
        const computed = window.getComputedStyle(wheelTrack).transform;
        if (!computed || computed === 'none') {
            return null;
        }
        const match = computed.match(/matrix\(([^)]+)\)/);
        if (!match) {
            return null;
        }
        const values = match[1].split(',').map((value) => Number(value.trim()));
        const translateX = values.length >= 6 ? values[4] : 0;
        return -translateX;
    };

    const getLoopSize = () => {
        if (!wheelTrack) {
            return null;
        }
        const tiles = wheelTrack.querySelectorAll('.wheel-tile');
        if (tiles.length === 0) {
            return null;
        }
        const tileWidth = tiles[0].offsetWidth + 6;
        const listSize = tiles.length / 3;
        return { tileWidth, listSize, loopPx: listSize * tileWidth };
    };

    const getLoop = () => {
        const loop = getLoopSize();
        if (loop) {
            loopCache = loop;
        }
        return loopCache;
    };

    const setTransformOffset = (offset) => {
        if (!wheelTrack) {
            return;
        }
        wheelTrack.style.transform = `translateX(${-offset}px)`;
    };

    const normalizeOffset = (offset, loopPx) => {
        const mod = ((offset % loopPx) + loopPx) % loopPx;
        return { mod, display: loopPx + mod };
    };

    const syncOffsetFromTransform = () => {
        const loop = getLoop();
        if (!loop) {
            return;
        }
        const currentOffset = getCurrentOffset();
        const normalized = normalizeOffset(currentOffset ?? 0, loop.loopPx);
        rollingOffset = normalized.mod;
        setTransformOffset(normalized.display);
    };

    const applyRollingOffset = () => {
        const loop = getLoop();
        if (!loop || rollingOffset === null) {
            return;
        }
        const display = loop.loopPx + rollingOffset;
        setTransformOffset(display);
    };

    const stopRolling = () => {
        if (rollingRafId !== null) {
            cancelAnimationFrame(rollingRafId);
            rollingRafId = null;
        }
        rollingLastTs = null;
    };

    const startRolling = () => {
        if (rollingRafId !== null || isSettling) {
            return;
        }
        const loop = getLoop();
        if (!loop) {
            return;
        }
        if (rollingOffset === null) {
            syncOffsetFromTransform();
        }

        const step = (ts) => {
            if (rollingLastTs === null) {
                rollingLastTs = ts;
            }
            const delta = ts - rollingLastTs;
            rollingLastTs = ts;

            if (rollingOffset !== null) {
                rollingOffset = (rollingOffset + delta * ROLL_SPEED_PX_PER_MS) % loop.loopPx;
                applyRollingOffset();
            }

            rollingRafId = requestAnimationFrame(step);
        };

        rollingRafId = requestAnimationFrame(step);
    };

    const settleToResult = (result) => {
        if (!wheelTrack || isSettling) {
            return;
        }
        const loop = getLoop();
        if (!loop) {
            return;
        }

        stopRolling();
        const targetIndex = loop.listSize + Math.max(0, Number(result));
        const viewport = wheelTrack.parentElement;
        const viewportWidth = viewport ? viewport.offsetWidth : 0;
        const baseOffset = (targetIndex * loop.tileWidth + loop.tileWidth / 2) - (viewportWidth / 2);

        if (rollingOffset === null) {
            syncOffsetFromTransform();
        }

        const baseMod = ((baseOffset % loop.loopPx) + loop.loopPx) % loop.loopPx;
        const currentMod = rollingOffset ?? 0;
        let delta = baseMod - currentMod;
        if (delta < 0) {
            delta += loop.loopPx;
        }
        const distance = delta + loop.loopPx * SPIN_MIN_LOOPS;
        const startOffset = rollingOffset ?? 0;
        const durationMs = Math.max(SPIN_DURATION_MS, Math.min(5200, distance * 1.1));
        const startTime = performance.now();
        isSettling = true;
        settleEndsAt = Date.now() + serverOffsetMs + durationMs;
        historyBlockedUntil = settleEndsAt;

        const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);

        const step = (ts) => {
            const elapsed = ts - startTime;
            const progress = Math.min(1, elapsed / durationMs);
            const eased = easeOutCubic(progress);
            const current = startOffset + distance * eased;
            rollingOffset = current % loop.loopPx;
            applyRollingOffset();

            if (progress < 1) {
                settleRafId = requestAnimationFrame(step);
            } else {
                isSettling = false;
                holdUntil = Date.now() + serverOffsetMs + RESULT_PAUSE_MS;
                settleRafId = null;
            }
        };

        settleRafId = requestAnimationFrame(step);
    };

    const updateResultVisibility = () => {
        if (!hasPendingResult() || revealAtMs === null) {
            return;
        }

        const now = Date.now() + serverOffsetMs;
        if (now >= revealAtMs && currentResult !== pendingResult) {
            currentResult = pendingResult;
            settleToResult(currentResult);
        }
    };

    const updateTimer = () => {
        if (!timerEl || !statusEl) {
            return;
        }

        const now = Date.now() + serverOffsetMs;
        const remainingMs = currentEndsAt - now;
        const remainingSec = Math.max(0, Math.ceil(remainingMs / 1000));

        const minutes = String(Math.floor(remainingSec / 60)).padStart(2, '0');
        const seconds = String(remainingSec % 60).padStart(2, '0');
        timerEl.textContent = `${minutes}:${seconds}`;

        const shouldRoll = remainingSec <= 0 && (!hasPendingResult() || (revealAtMs !== null && now < revealAtMs));
        const settleDone = !isSettling && now >= settleEndsAt;
        const isResetting = hasPendingResult() && settleDone && now < holdUntil;
        const isBettingClosed = remainingSec <= 3 || shouldRoll || isSettling || isResetting;

        if (remainingSec > 0) {
            statusEl.textContent = 'Zakłady otwarte';
            stopRolling();
        } else if (shouldRoll) {
            statusEl.textContent = 'ROLLING...';
            startRolling();
        } else if (!settleDone) {
            statusEl.textContent = 'ROLLING...';
            stopRolling();
        } else if (isResetting) {
            updateResultVisibility();
            statusEl.textContent = `Wynik: ${pendingResult} • resetowanie maszyny do scamowania`;
            stopRolling();
        } else if (hasPendingResult()) {
            updateResultVisibility();
            statusEl.textContent = `Wynik: ${pendingResult}`;
            stopRolling();
        } else {
            statusEl.textContent = 'Oczekiwanie...';
            startRolling();
        }

        if (placeBetBtn) {
            placeBetBtn.disabled = isBettingClosed;
            placeBetBtn.classList.toggle('is-disabled', isBettingClosed);
        }
    };

    const positionWheel = (result) => {
        const loop = getLoop();
        if (!loop) {
            return;
        }
        const targetIndex = loop.listSize + Math.max(0, Number(result));
        const viewport = wheelTrack?.parentElement;
        const viewportWidth = viewport ? viewport.offsetWidth : 0;
        const baseOffset = (targetIndex * loop.tileWidth + loop.tileWidth / 2) - (viewportWidth / 2);
        const normalized = normalizeOffset(baseOffset, loop.loopPx);
        rollingOffset = normalized.mod;
        setTransformOffset(normalized.display);
    };

    const renderHistory = (items) => {
        if (!historyEl) {
            return;
        }
        historyEl.innerHTML = '';
        if (!items.length) {
            const empty = document.createElement('span');
            empty.className = 'history-empty';
            empty.textContent = 'Brak wyników.';
            historyEl.appendChild(empty);
            return;
        }

        const ordered = items.slice().reverse();
        ordered.forEach((item, index) => {
            const badge = document.createElement('span');
            const isLatest = index === ordered.length - 1;
            badge.className = `history-item ${item.color}${isLatest ? ' is-latest' : ''}`;
            badge.textContent = item.number;
            historyEl.appendChild(badge);
        });
    };

    const refreshState = async () => {
        if (!stateUrl) {
            return;
        }

        try {
            const response = await fetch(stateUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            updateOffset(data.serverTime);

            if (data.round) {
                const now = Date.now() + serverOffsetMs;
                const nextRoundId = String(data.round.id);
                if (nextRoundId !== currentRoundId && holdUntil > now) {
                    queuedRound = data.round;
                } else {
                    queuedRound = null;
                    if (nextRoundId !== currentRoundId) {
                        currentRoundId = nextRoundId;
                        currentResult = '';
                        pendingResult = '';
                        revealAtMs = null;
                        isSettling = false;
                        settleEndsAt = 0;
                        holdUntil = 0;
                        pendingBalance = null;
                        loadRoundBet();
                    }

                    currentEndsAt = new Date(data.round.endsAt).getTime();
                    const nextResult = normalizeResult(data.round.resultNumber);
                    if (nextResult !== pendingResult) {
                        pendingResult = nextResult;
                        revealAtMs = pendingResult !== '' ? currentEndsAt + REVEAL_DELAY_MS : null;
                    }

                    updateResultVisibility();
                }
            }

            if (Array.isArray(data.history) && Date.now() + serverOffsetMs >= historyBlockedUntil) {
                renderHistory(data.history);
            }
            if (balanceEl && typeof data.balance === 'number') {
                const now = Date.now() + serverOffsetMs;
                if (settleEndsAt > now) {
                    pendingBalance = data.balance;
                } else {
                    balanceEl.textContent = String(data.balance);
                    pendingBalance = null;
                }
            }
        } catch (error) {
            console.warn('Roulette state error', error);
        }
    };

    setSelected('color', 'red');
    updateBetSummary();
    loadRoundBet();
    updateTimer();
    const timerInterval = setInterval(updateTimer, 500);
    const stateInterval = setInterval(() => {
        if (queuedRound && Date.now() + serverOffsetMs >= holdUntil) {
            const nextRoundId = String(queuedRound.id);
            if (nextRoundId !== currentRoundId) {
                currentRoundId = nextRoundId;
                currentResult = '';
                pendingResult = '';
                revealAtMs = null;
                isSettling = false;
                settleEndsAt = 0;
                holdUntil = 0;
                pendingBalance = null;
                loadRoundBet();
            }
            currentEndsAt = new Date(queuedRound.endsAt).getTime();
            queuedRound = null;
        }
        if (balanceEl && pendingBalance !== null && Date.now() + serverOffsetMs >= settleEndsAt) {
            balanceEl.textContent = String(pendingBalance);
            pendingBalance = null;
        }
        refreshState();
    }, 2000);

    return () => {
        clearInterval(timerInterval);
        clearInterval(stateInterval);
        stopRolling();
        if (settleRafId !== null) {
            cancelAnimationFrame(settleRafId);
            settleRafId = null;
        }
    };
};

const bootRoulette = () => {
    if (rouletteCleanup) {
        rouletteCleanup();
        rouletteCleanup = null;
    }
    rouletteCleanup = initRoulette();
};

document.addEventListener('turbo:load', bootRoulette);
document.addEventListener('turbo:before-cache', () => {
    if (rouletteCleanup) {
        rouletteCleanup();
        rouletteCleanup = null;
    }
});
