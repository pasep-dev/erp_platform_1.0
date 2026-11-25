<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    domain: 'asset-management' | 'procurement' | 'inventory-management'
    title: string
    number?: string | null
    companyName?: string | null
    dateLabel?: string | null
    statusLabel?: string | null
}>(), {
    companyName: null,
    number: null,
    dateLabel: null,
    statusLabel: null,
})

const domainLabel = computed(() => ({
    'asset-management': 'Asset Management',
    procurement: 'Procurement',
    'inventory-management': 'Inventory Management',
})[props.domain])

const accentColor = computed(() => ({
    'asset-management': '#4c1d95',
    procurement: '#1e3a8a',
    'inventory-management': '#065f46',
})[props.domain])
</script>

<template>
    <header class="print-document-header" :style="{ '--print-accent': accentColor }">
        <div class="print-document-heading">
            <p class="print-document-domain">{{ domainLabel }}</p>
            <p v-if="companyName" class="print-document-company">{{ companyName }}</p>
            <h1>{{ title }}</h1>
        </div>
        <div v-if="number || dateLabel || statusLabel" class="print-document-reference">
            <span v-if="number">No. Dokumen</span>
            <strong v-if="number">{{ number }}</strong>
            <span v-if="dateLabel">{{ dateLabel }}</span>
            <span v-if="statusLabel" class="print-document-status">{{ statusLabel }}</span>
        </div>
    </header>
</template>

<style>
.print-document-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 2px solid var(--print-accent);
    padding-bottom: 12px;
    color: #111827;
    font-family: Arial, Helvetica, sans-serif;
}

.print-document-heading { min-width: 0; }
.print-document-domain { margin: 0 0 4px; color: var(--print-accent); font-size: 9px; font-weight: 700; }
.print-document-company { margin: 0 0 5px; color: #374151; font-size: 11px; font-weight: 600; }
.print-document-header h1 { margin: 0; font-size: 16px; font-weight: 700; line-height: 1.3; }
.print-document-reference { display: grid; justify-items: end; gap: 3px; text-align: right; font-size: 9px; }
.print-document-reference > span { color: #6b7280; }
.print-document-reference strong { color: var(--print-accent); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10px; }
.print-document-reference .print-document-status { color: var(--print-accent); font-weight: 700; }

@media print {
    .print-document-header { break-inside: avoid; }
}
</style>
