/**
 * Carousel V2 JavaScript - Slider con Loop Infinito
 * Trencito de imágenes con loop continuo hacia la izquierda
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCarouselV2);
    } else {
        initCarouselV2();
    }

    function initCarouselV2() {

        const wrapper = document.querySelector('.carousel-v2-wrapper');
        const container = document.querySelector('.carousel-v2-container');
        const track = document.getElementById('carousel-track');

        if (!wrapper || !container || !track) {
            return;
        }

        const carouselData = window.carouselV2Data || {};
        const slides = carouselData.slides || [];
        const originalSlidesCount = slides.length;


        if (originalSlidesCount === 0) {
            return;
        }

        // Start at the middle set (set 2 of 5) to allow infinite scrolling in both directions
        let currentIndex = originalSlidesCount * 2; // Start at 3rd copy
        let isPaused = false;
        let autoAdvanceTimer = null;
        const autoAdvanceTime = carouselData.autoAdvanceTime || 5000;
        let slideElements = null;

        // Wait for images to load before calculating positions
        const images = track.querySelectorAll('.carousel-v2-image');
        let loadedImages = 0;

        function onImageLoad() {
            loadedImages++;
            if (loadedImages === images.length) {
                initializeCarousel();
            }
        }

        images.forEach(img => {
            if (img.complete) {
                onImageLoad();
            } else {
                img.addEventListener('load', onImageLoad);
                img.addEventListener('error', onImageLoad);
            }
        });

        function initializeCarousel() {
            slideElements = track.querySelectorAll('.carousel-v2-slide');
            const totalRenderedSlides = slideElements.length;

            // Create dots navigation (only for original slides)
            const dotsContainer = document.createElement('div');
            dotsContainer.className = 'carousel-v2-dots';

            for (let i = 0; i < originalSlidesCount; i++) {
                const dot = document.createElement('button');
                dot.className = 'carousel-v2-dot';
                dot.setAttribute('aria-label', `Ir a slide ${i + 1}`);
                dot.addEventListener('click', () => goToSlide(i));
                dotsContainer.appendChild(dot);
            }

            container.appendChild(dotsContainer);
            const dots = dotsContainer.querySelectorAll('.carousel-v2-dot');

            /**
             * Go to specific slide (by original index)
             */
            function goToSlide(originalIndex) {
                // Find the closest slide with this original index from current position
                const currentSet = Math.floor(currentIndex / originalSlidesCount);
                currentIndex = currentSet * originalSlidesCount + originalIndex;
                updateCarousel();
                resetAutoAdvance();
            }

            /**
             * Go to next slide
             */
            function nextSlide() {
                currentIndex++;

                // Check if we need to loop (after 4th set, jump back to 2nd set)
                // This happens without transition after the transition completes
                if (currentIndex >= originalSlidesCount * 4) {
                    // Schedule the instant jump after transition
                    setTimeout(() => {
                        track.style.transition = 'none';
                        currentIndex = originalSlidesCount * 2 + (currentIndex % originalSlidesCount);
                        updateCarousel(true);

                        // Re-enable transition after a frame
                        requestAnimationFrame(() => {
                            track.style.transition = '';
                        });
                    }, 600); // Wait for transition to complete
                }

                updateCarousel();
            }

            /**
             * Update carousel position and dots
             */
            function updateCarousel(skipTransition = false) {
                if (!slideElements || slideElements.length === 0) return;

                // Get container width
                const containerWidth = container.offsetWidth;

                // Calculate offset to center the current slide
                let offset = containerWidth / 2;

                // Subtract half of current slide width
                const currentSlide = slideElements[currentIndex];
                const currentSlideWidth = currentSlide.offsetWidth;
                offset -= currentSlideWidth / 2;

                // Subtract widths of all previous slides
                for (let i = 0; i < currentIndex; i++) {
                    offset -= slideElements[i].offsetWidth;
                }

                // Apply transform
                track.style.transform = `translateX(${offset}px)`;

                // Update dots based on original index
                const originalIndex = currentIndex % originalSlidesCount;
                dots.forEach((dot, index) => {
                    if (index === originalIndex) {
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
                    }
                });

            }

            /**
             * Start auto-advance timer
             */
            function startAutoAdvance() {
                if (autoAdvanceTimer) {
                    clearInterval(autoAdvanceTimer);
                }

                autoAdvanceTimer = setInterval(() => {
                    if (!isPaused) {
                        nextSlide();
                    }
                }, autoAdvanceTime);
            }

            /**
             * Reset auto-advance timer
             */
            function resetAutoAdvance() {
                startAutoAdvance();
            }

            /**
             * Pause animation
             */
            function pause() {
                isPaused = true;
            }

            /**
             * Resume animation
             */
            function resume() {
                isPaused = false;
            }

            // Pause on hover
            wrapper.addEventListener('mouseenter', pause);
            wrapper.addEventListener('mouseleave', resume);

            // Pause when tab is not visible
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    pause();
                } else {
                    resume();
                }
            });

            // Recalculate on window resize
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    updateCarousel();
                }, 250);
            });

            // Initialize carousel
            updateCarousel();
            startAutoAdvance();

        }
    }
})();
