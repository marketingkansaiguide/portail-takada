<div id="takada-page-navigation-feedback" aria-live="polite" aria-busy="false">
    <span style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"><?php echo e(__('Chargement de la page')); ?></span>
</div>

<style>
    #takada-page-navigation-feedback {
        position: fixed;
        z-index: 1000;
        top: 0;
        right: 0;
        left: 0;
        height: 3px;
        pointer-events: none;
        visibility: hidden;
        opacity: 0;
        transition: opacity 150ms ease, visibility 150ms ease;
    }

    #takada-page-navigation-feedback::before {
        position: absolute;
        top: 0;
        left: 0;
        width: 38%;
        height: 100%;
        border-radius: 0 999px 999px 0;
        background: #0f766e;
        box-shadow: 0 0 12px rgb(13 148 136 / 55%);
        content: '';
        transform: translateX(-120%);
    }

    #takada-page-navigation-feedback.is-active {
        visibility: visible;
        opacity: 1;
    }

    #takada-page-navigation-feedback.is-active::before {
        animation: takada-page-navigation-progress 900ms cubic-bezier(.37, 0, .63, 1) infinite;
    }

    body.takada-page-is-navigating .fi-main {
        animation: takada-page-content-transition 180ms ease-out both;
    }

    body.takada-sidebar-is-navigating .fi-main-sidebar .fi-sidebar-nav {
        visibility: hidden;
    }

    @keyframes takada-page-navigation-progress {
        from { transform: translateX(-120%); }
        to { transform: translateX(290%); }
    }

    @keyframes takada-page-content-transition {
        from { opacity: 1; }
        to { opacity: .9; }
    }

    @media (prefers-reduced-motion: reduce) {
        #takada-page-navigation-feedback.is-active::before,
        body.takada-page-is-navigating .fi-main {
            animation: none;
        }
    }
</style>

<script data-navigate-once>
    (() => {
        const minimumDisplayDuration = 220
        const sidebarSelector = '.fi-main-sidebar .fi-sidebar-nav'
        let startedAt = 0
        let navigationId = 0
        let sidebarScrollTop = null
        let sidebarNavigationInProgress = false
        const prefetchedUrls = new Set()

        const getFeedback = () => document.getElementById('takada-page-navigation-feedback')

        const rememberSidebarScroll = () => {
            if (sidebarNavigationInProgress) {
                return
            }

            const sidebar = document.querySelector(sidebarSelector)

            if (sidebar) {
                sidebarScrollTop = sidebar.scrollTop
                sidebarNavigationInProgress = true
            }
        }

        const restoreSidebarScroll = () => {
            const savedSidebarScrollTop = sidebarScrollTop

            if (! Number.isFinite(savedSidebarScrollTop)) {
                sidebarNavigationInProgress = false
                document.body.classList.remove('takada-sidebar-is-navigating')
                return
            }

            const sidebar = document.querySelector(sidebarSelector)

            if (! sidebar) {
                sidebarNavigationInProgress = false
                document.body.classList.remove('takada-sidebar-is-navigating')
                return
            }

            // Restaurer immédiatement évite que le navigateur affiche la sidebar à 0
            // entre le remplacement SPA et la restauration Filament différée.
            sidebar.scrollTop = savedSidebarScrollTop

            // Le contenu peut encore être recalculé après le morph de la page :
            // on conserve une restauration de sécurité au frame suivant.
            window.requestAnimationFrame(() => {
                const currentSidebar = document.querySelector(sidebarSelector)

                if (currentSidebar) {
                    currentSidebar.scrollTop = savedSidebarScrollTop
                }

                window.requestAnimationFrame(() => {
                    const finalSidebar = document.querySelector(sidebarSelector)

                    if (finalSidebar) {
                        finalSidebar.scrollTop = savedSidebarScrollTop
                    }

                    sidebarScrollTop = null
                    sidebarNavigationInProgress = false
                    document.body.classList.remove('takada-sidebar-is-navigating')
                })
            })
        }

        const freezeSidebar = () => {
            if (! Number.isFinite(sidebarScrollTop)) {
                rememberSidebarScroll()
            }

            if (Number.isFinite(sidebarScrollTop)) {
                document.body.classList.add('takada-sidebar-is-navigating')
            }
        }

        const start = () => {
            const feedback = getFeedback()

            navigationId += 1
            startedAt = Date.now()
            feedback?.classList.add('is-active')
            feedback?.setAttribute('aria-busy', 'true')
            document.body.classList.add('takada-page-is-navigating')
        }

        const finish = () => {
            const completedNavigationId = navigationId

            window.setTimeout(() => {
                if (completedNavigationId !== navigationId) {
                    return
                }

                const feedback = getFeedback()

                feedback?.classList.remove('is-active')
                feedback?.setAttribute('aria-busy', 'false')
                document.body.classList.remove('takada-page-is-navigating')
            }, Math.max(0, minimumDisplayDuration - (Date.now() - startedAt)))
        }

        const isPrefetchableUrl = (url) => {
            if (! url) {
                return false
            }

            if (url.startsWith('#') || url.startsWith('mailto:') || url.startsWith('tel:')) {
                return false
            }

            try {
                const destination = new URL(url, window.location.href)

                return destination.origin === window.location.origin
                    && destination.href !== window.location.href
                    && ! /\/(?:logout|download-file|pdf)(?:\/|$)/.test(destination.pathname)
            } catch {
                return false
            }
        }

        const isPrefetchableLink = (link) => link
            && ! link.target
            && ! link.hasAttribute('download')
            && link.dataset.takadaPrefetch !== 'disabled'
            && isPrefetchableUrl(link.href)

        const prefetchUrl = (url) => {
            if (! isPrefetchableUrl(url) || prefetchedUrls.has(url)) {
                return
            }

            // Préchargement navigateur sans modifier le comportement Livewire
            // des liens ni injecter de directive dans les vues personnalisées.
            const prefetch = document.createElement('link')
            prefetch.rel = 'prefetch'
            prefetch.as = 'document'
            prefetch.href = url
            document.head.append(prefetch)
            prefetchedUrls.add(url)
        }

        const prefetchLink = (link) => {
            if (isPrefetchableLink(link)) {
                prefetchUrl(link.href)
            }
        }

        const feedback = getFeedback()

        feedback?.setAttribute('data-ready', 'true')

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return
            }

            const link = event.target.closest?.('a[href]')
            const productCard = event.target.closest?.('[data-takada-page-link]')

            if (isPrefetchableLink(link) || isPrefetchableUrl(productCard?.dataset.takadaPageLink)) {
                start()
            }
        }, {capture: true})

        document.addEventListener('pointerover', (event) => {
            prefetchLink(event.target.closest?.('a[href]'))
            prefetchUrl(event.target.closest?.('[data-takada-page-link]')?.dataset.takadaPageLink)
        }, {passive: true})
        document.addEventListener('livewire:navigate', rememberSidebarScroll, {capture: true})
        document.addEventListener('livewire:navigating', rememberSidebarScroll, {capture: true})
        document.addEventListener('livewire:navigated', restoreSidebarScroll, {capture: true})
        document.addEventListener('livewire:navigating', freezeSidebar, {capture: true})
        document.addEventListener('livewire:navigating', start, {capture: true})
        document.addEventListener('livewire:navigated', finish)
    })()
</script>
<?php /**PATH C:\Users\marke\Herd\portail-takada\resources\views\filament\partials\page-navigation-feedback.blade.php ENDPATH**/ ?>