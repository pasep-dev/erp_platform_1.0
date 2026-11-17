<script setup></script>

<template>
    <footer class="layout-footer">
        <div class="footer-left">
            <span class="footer-divider">•</span>

            <span class="footer-subtitle"> </span>
        </div>

        <div class="footer-right">
            <span>Asset Management & Tracking System V1.0</span>
        </div>
    </footer>
</template>

<style scoped>
.layout-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;

    width: calc(100% + 4rem);
    margin-left: -2rem;

    min-height: 40px;
    padding: 0.5rem 2rem;

    border-top: 1px solid var(--surface-border);
    background: var(--surface-card);

    color: var(--text-color-secondary);
    font-size: 0.875rem;

    margin-top: auto;
}

.footer-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.footer-title {
    font-weight: 600;
    letter-spacing: 0.08em;
    color: var(--text-color);
}

.footer-c {
    color: #a626a6;
}

.footer-divider {
    opacity: 0.5;
}

.footer-subtitle {
    font-size: 0.8rem;
}

.footer-right {
    font-size: 0.8rem;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .layout-footer {
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        padding: 0.75rem 1rem;
    }

    .footer-divider {
        display: none;
    }

    .footer-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.15rem;
    }

    .footer-right {
        margin-top: 0.35rem;
    }
}
</style>
