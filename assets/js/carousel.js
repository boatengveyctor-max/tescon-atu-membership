(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-carousel-track]');
        const dotsContainer = carousel.querySelector('[data-carousel-dots]');
        const previousButton = carousel.querySelector('[data-carousel-prev]');
        const nextButton = carousel.querySelector('[data-carousel-next]');
        if (!track || !dotsContainer || !previousButton || !nextButton) {
            return;
        }

        const autoplayDelay = Number.parseInt(carousel.dataset.autoplay || '0', 10);
        let pageStarts = [0];
        let currentPage = 0;
        let timer = null;
        let hovered = false;
        let focused = false;
        let touchStartX = null;
        let touchStartPage = 0;

        const visibleCards = () => Array.from(track.querySelectorAll('[data-carousel-card]'))
            .filter((card) => !card.hidden);

        const getStep = (cards) => {
            if (!cards.length) {
                return 0;
            }

            const styles = window.getComputedStyle(track);
            const gap = Number.parseFloat(styles.columnGap || styles.gap) || 0;
            return cards[0].getBoundingClientRect().width + gap;
        };

        const pageAtScrollPosition = (cards, step) => {
            if (!cards.length || step === 0) {
                return 0;
            }

            let nearestPage = 0;
            let nearestDistance = Number.POSITIVE_INFINITY;
            pageStarts.forEach((start, page) => {
                const distance = Math.abs(track.scrollLeft - start * step);
                if (distance < nearestDistance) {
                    nearestDistance = distance;
                    nearestPage = page;
                }
            });
            return nearestPage;
        };

        const updateControls = () => {
            const cards = visibleCards();
            const step = getStep(cards);
            currentPage = pageAtScrollPosition(cards, step);
            previousButton.disabled = pageStarts.length <= 1 || currentPage === 0;
            nextButton.disabled = pageStarts.length <= 1 || currentPage === pageStarts.length - 1;

            Array.from(dotsContainer.children).forEach((dot, page) => {
                if (page === currentPage) {
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.removeAttribute('aria-current');
                }
            });
        };

        const goToPage = (page) => {
            const cards = visibleCards();
            const step = getStep(cards);
            if (!cards.length || step === 0) {
                return;
            }

            currentPage = Math.max(0, Math.min(page, pageStarts.length - 1));
            track.scrollTo({
                left: pageStarts[currentPage] * step,
                behavior: reducedMotion.matches ? 'auto' : 'smooth'
            });
            updateControls();
        };

        const renderDots = () => {
            dotsContainer.replaceChildren();
            pageStarts.forEach((start, page) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'carousel-dot';
                dot.setAttribute('aria-label', `Show page ${page + 1} of ${pageStarts.length}`);
                dot.addEventListener('click', () => goToPage(page));
                dotsContainer.appendChild(dot);
            });
            dotsContainer.hidden = pageStarts.length <= 1;
        };

        const refresh = (reset = false) => {
            const cards = visibleCards();
            const step = getStep(cards);
            const gap = cards.length
                ? (Number.parseFloat(window.getComputedStyle(track).columnGap) || 0)
                : 0;
            const visibleCount = cards.length && step
                ? Math.max(1, Math.floor((track.clientWidth + gap + 1) / step))
                : 1;
            const lastStart = Math.max(0, cards.length - visibleCount);

            pageStarts = [0];
            for (let start = visibleCount; start < lastStart; start += visibleCount) {
                pageStarts.push(start);
            }
            if (lastStart > pageStarts[pageStarts.length - 1]) {
                pageStarts.push(lastStart);
            }

            renderDots();
            if (reset) {
                track.scrollLeft = 0;
            } else if (currentPage >= pageStarts.length) {
                currentPage = pageStarts.length - 1;
                goToPage(currentPage);
            }
            updateControls();
            syncAutoplay();
        };

        const stopAutoplay = () => {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        };

        const syncAutoplay = () => {
            stopAutoplay();
            if (
                autoplayDelay > 0
                && pageStarts.length > 1
                && !reducedMotion.matches
                && !hovered
                && !focused
                && !document.hidden
            ) {
                timer = window.setInterval(() => {
                    goToPage((currentPage + 1) % pageStarts.length);
                }, autoplayDelay);
            }
        };

        previousButton.addEventListener('click', () => {
            goToPage(currentPage - 1);
            syncAutoplay();
        });
        nextButton.addEventListener('click', () => {
            goToPage(currentPage + 1);
            syncAutoplay();
        });

        track.addEventListener('scroll', () => {
            window.requestAnimationFrame(updateControls);
        }, { passive: true });

        track.addEventListener('touchstart', (event) => {
            touchStartX = event.changedTouches[0]?.clientX ?? null;
            touchStartPage = currentPage;
        }, { passive: true });
        track.addEventListener('touchend', (event) => {
            if (touchStartX === null) {
                return;
            }
            const distance = event.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(distance) > 48) {
                goToPage(touchStartPage + (distance < 0 ? 1 : -1));
            }
        }, { passive: true });

        carousel.addEventListener('mouseenter', () => {
            hovered = true;
            syncAutoplay();
        });
        carousel.addEventListener('mouseleave', () => {
            hovered = false;
            syncAutoplay();
        });
        carousel.addEventListener('focusin', () => {
            focused = true;
            syncAutoplay();
        });
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) {
                focused = false;
                syncAutoplay();
            }
        });

        // Region filters live in the parent .leadership-section header, not inside
        // the carousel element, so search the closest section ancestor.
        const filterScope = carousel.closest('.leadership-section') ?? carousel;
        filterScope.querySelectorAll('[data-region-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                const selectedRegion = button.dataset.regionFilter;
                filterScope.querySelectorAll('[data-region-filter]').forEach((filter) => {
                    const selected = filter === button;
                    filter.classList.toggle('is-active', selected);
                    filter.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });

                carousel.querySelectorAll('[data-carousel-card]').forEach((card) => {
                    card.hidden = selectedRegion !== 'all' && card.dataset.region !== selectedRegion;
                });
                refresh(true);
            });
        });

        document.addEventListener('visibilitychange', syncAutoplay);
        window.addEventListener('resize', () => refresh());
        reducedMotion.addEventListener?.('change', syncAutoplay);
        refresh(true);
    });
})();
