<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import miniSvg from "@/../images/logo/citra-logo.svg";

const page = usePage();
</script>

<template>
    <main class="guest-page">
        <section class="guest-brand-panel" aria-label="Citra Group">
            <div class="guest-brand-lockup">
                <span class="guest-brand-logo-frame">
                    <img :src="miniSvg" alt="" class="guest-brand-logo" />
                </span>
                <span class="guest-brand-name">CITRA GROUP</span>
            </div>

            <div class="guest-brand-message">
                <h1>{{ page.props.app.name }}</h1>
                <p>Asset Management &amp; Tracking System</p>
            </div>
        </section>

        <section class="guest-form-panel" aria-labelledby="guest-form-title">
            <div class="guest-form-content">
                <div class="guest-form-card">
                    <slot />
                </div>

                <p class="guest-footer">
                    &copy; 2026 {{ page.props.app.name }}. Development
                </p>
            </div>
        </section>
    </main>
</template>

<style scoped>
.guest-page {
    display: grid;
    min-height: 100vh;
    grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr);
    background: var(--app-page-background, #f5f5f9);
    color: var(--app-text-primary, #384551);
}

.guest-brand-panel {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 3rem;
    padding: clamp(2rem, 6vw, 6.5rem);
    background: linear-gradient(145deg, #383b91 0%, #5558ce 58%, #696cff 100%);
    color: #fff;
}

.guest-brand-lockup {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.guest-brand-logo-frame {
    display: grid;
    width: 3.75rem;
    height: 3.75rem;
    flex: 0 0 3.75rem;
    place-items: center;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 0.5rem 1.5rem rgb(24 27 81 / 18%);
}

.guest-brand-logo {
    width: 2.5rem;
    height: 2.5rem;
    object-fit: contain;
}

.guest-brand-name {
    font-size: 0.9375rem;
    font-weight: 700;
    letter-spacing: 0.14em;
}

.guest-brand-message {
    max-width: 34rem;
}

.guest-brand-message h1 {
    margin: 0;
    color: #fff;
    font-size: clamp(2.5rem, 4.5vw, 4rem);
    font-weight: 700;
    letter-spacing: -0.045em;
    line-height: 1.08;
}

.guest-brand-message p {
    max-width: 26rem;
    margin: 1.25rem 0 0;
    color: rgb(255 255 255 / 84%);
    font-size: clamp(1rem, 1.4vw, 1.25rem);
    line-height: 1.6;
}

.guest-form-panel {
    display: grid;
    min-width: 0;
    place-items: center;
    padding: clamp(1.5rem, 5vw, 5rem);
}

.guest-form-content {
    width: min(100%, 30rem);
}

.guest-form-card {
    padding: clamp(1.5rem, 4vw, 3rem);
    border: 1px solid var(--app-border, #e7e7f0);
    border-radius: 1rem;
    background: var(--app-surface, #fff);
    box-shadow: 0 1.25rem 3.5rem rgb(46 51 84 / 10%);
}

.guest-footer {
    margin: 1.5rem 0 0;
    color: var(--app-text-muted, #9299a5);
    font-size: 0.9375rem;
    text-align: center;
}

:global(.app-dark) .guest-form-card {
    box-shadow: 0 1.25rem 3.5rem rgb(0 0 0 / 20%);
}

@media (max-width: 767px) {
    .guest-page {
        grid-template-columns: minmax(0, 1fr);
    }

    .guest-brand-panel {
        justify-content: flex-start;
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .guest-brand-logo-frame {
        width: 3rem;
        height: 3rem;
        flex-basis: 3rem;
        border-radius: 0.75rem;
    }

    .guest-brand-logo {
        width: 2rem;
        height: 2rem;
    }

    .guest-brand-message h1 {
        font-size: 2rem;
    }

    .guest-brand-message p {
        margin-top: 0.625rem;
        font-size: 1rem;
    }

    .guest-form-panel {
        align-items: start;
        padding: 1.5rem 1rem 2rem;
    }
}
</style>
