<script setup lang="ts">
import { computed } from 'vue'
import { createAssetQrCode } from './qrCode'

const props = withDefaults(defineProps<{
    value: string
    companyCode?: string
    showLabel?: boolean
}>(), {
    companyCode: '',
    showLabel: true,
})

const modules = computed(() => createAssetQrCode(props.value, props.companyCode ? 5 : 1))
const size = computed(() => modules.value.length)
const path = computed(() => modules.value.flatMap((row, y) => row.flatMap((dark, x) => dark ? [`M${x + 4} ${y + 4}h1v1h-1z`] : [])).join(''))
const centerCompanyCode = computed(() => props.companyCode.trim().toUpperCase())
const companyCodeFontSize = computed(() => centerCompanyCode.value.length > 4 ? 1.8 : centerCompanyCode.value.length > 3 ? 2.3 : 2.8)
const center = computed(() => (size.value + 8) / 2)
</script>

<template>
    <div class="asset-qr-code inline-flex flex-col items-center rounded-xl border-2 border-purple-900 bg-white p-2 text-center dark:border-purple-800">
        <svg :viewBox="`0 0 ${size + 8} ${size + 8}`" role="img" :aria-label="`QR code aset ${value}${companyCode ? ` perusahaan ${companyCode}` : ''}`" class="max-w-full" :class="companyCode ? 'h-44 w-44' : 'h-36 w-36'" shape-rendering="crispEdges">
            <rect width="100%" height="100%" fill="#fff" />
            <path :d="path" fill="#111827" />
            <g v-if="centerCompanyCode">
                <rect :x="center - 6.5" :y="center - 2.4" width="13" height="4.8" rx="0.8" fill="#fff" stroke="#4c1d95" stroke-width="0.4" />
                <text :x="center" :y="center + 0.05" text-anchor="middle" dominant-baseline="central" fill="#4c1d95" font-family="Arial, sans-serif" :font-size="companyCodeFontSize" font-weight="700">{{ centerCompanyCode }}</text>
            </g>
        </svg>
        <span v-if="showLabel" class="mt-1 font-mono text-xs font-semibold tracking-wider text-gray-900">{{ value }}</span>
    </div>
</template>
