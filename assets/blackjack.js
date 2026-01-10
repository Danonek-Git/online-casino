const getSoundMap = () => {
    const page = document.querySelector('.blackjack-page');
    if (!page || !page.dataset.sounds) return null;
    try {
        const parsed = JSON.parse(page.dataset.sounds);
        if (parsed && typeof parsed === 'object') {
            return parsed;
        }
    } catch (e) {
        // Ignore invalid JSON
    }
    return null;
};

const playSound = (type) => {
    try {
        const sounds = getSoundMap();
        if (!sounds) return;
        let src = sounds[type];
        if (Array.isArray(src)) {
            src = src[Math.floor(Math.random() * src.length)];
        }
        if (src) {
            const audio = new Audio(src);
            audio.volume = 0.5;
            audio.play().catch(() => {});
        }
    } catch (e) {
        // Audio not supported
    }
};

const RESULT_CONFIG = {
    win: {
        icon: '✓',
        title: 'Wygrana!',
        message: 'Gratulacje! Pokonałeś krupiera.'
    },
    blackjack: {
        icon: '★',
        title: 'Blackjack!',
        message: 'Niesamowite! Trafiony blackjack!'
    },
    push: {
        icon: '=',
        title: 'Remis',
        message: 'Zakład zostaje zwrócony.'
    },
    lose: {
        icon: '✗',
        title: 'Przegrana',
        message: 'Tym razem nie udało się.'
    }
};

const initBlackjack = () => {
    const page = document.querySelector('.blackjack-page');
    if (!page) {
        return null;
    }
    if (page.dataset.blackjackInit === 'true') {
        return null;
    }
    page.dataset.blackjackInit = 'true';

    const amountInput = page.querySelector('[data-amount-input]');
    const balanceEl = page.querySelector('[data-balance]');
    const modal = page.querySelector('[data-result-modal]');

    page.querySelectorAll('[data-amount-add]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!amountInput) return;
            const add = Number(btn.dataset.amountAdd);
            const current = Number(amountInput.value || 0);
            amountInput.value = current + add;
        });
    });

    page.querySelectorAll('[data-amount-mul]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!amountInput) return;
            const mul = Number(btn.dataset.amountMul);
            const current = Number(amountInput.value || 0);
            amountInput.value = Math.max(0, Math.floor(current * mul));
        });
    });

    const clearBtn = page.querySelector('[data-amount-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (!amountInput) return;
            amountInput.value = '';
        });
    }

    const maxBtn = page.querySelector('[data-amount-max]');
    if (maxBtn) {
        maxBtn.addEventListener('click', () => {
            if (!amountInput || !balanceEl) return;
            const balance = Number(balanceEl.textContent.trim());
            amountInput.value = Number.isNaN(balance) ? '' : balance;
        });
    }

    // Play card sounds on deal/hit button clicks
    const dealForm = page.querySelector('form[action*="deal"]');
    if (dealForm) {
        dealForm.addEventListener('submit', () => {
            playSound('shuffle');
        });
    }

    const hitForm = page.querySelector('form[action*="hit"]');
    if (hitForm) {
        hitForm.addEventListener('submit', () => {
            playSound('card');
        });
    }

    const standForm = page.querySelector('form[action*="stand"]');
    if (standForm) {
        standForm.addEventListener('submit', () => {
            playSound('card');
        });
    }

    // Result modal
    const result = page.dataset.result;
    const payout = page.dataset.payout;
    const bet = page.dataset.bet;

    if (result && modal) {
        const config = RESULT_CONFIG[result] || RESULT_CONFIG.lose;

        modal.classList.add('is-visible', `result-${result}`);

        // Play result sound
        if (result === 'blackjack') {
            playSound('blackjack');
        } else if (result === 'win') {
            playSound('win');
        } else if (result === 'push') {
            playSound('push');
        } else {
            playSound('lose');
        }

        const iconEl = modal.querySelector('[data-modal-icon]');
        const titleEl = modal.querySelector('[data-modal-title]');
        const messageEl = modal.querySelector('[data-modal-message]');
        const payoutEl = modal.querySelector('[data-modal-payout]');

        if (iconEl) iconEl.textContent = config.icon;
        if (titleEl) titleEl.textContent = config.title;
        if (messageEl) messageEl.textContent = config.message;

        if (payoutEl) {
            if (result === 'win' || result === 'blackjack') {
                payoutEl.textContent = `+${payout} zł`;
            } else if (result === 'push') {
                payoutEl.textContent = `${payout} zł zwrot`;
            } else {
                payoutEl.textContent = `-${bet} zł`;
            }
        }

        const closeBtn = modal.querySelector('[data-modal-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.classList.remove('is-visible');
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('is-visible');
            }
        });
    }

    return null;
};

document.addEventListener('turbo:load', initBlackjack);
