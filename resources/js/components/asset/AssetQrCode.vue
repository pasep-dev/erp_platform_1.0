<script setup lang="ts">
import { computed } from 'vue'
import { createAssetQrCode } from './qrCode'

const props = withDefaults(defineProps<{
    value: string
    showLabel?: boolean
}>(), {
    showLabel: true,
})

const modules = computed(() => createAssetQrCode(props.value))
const size = computed(() => modules.value.length)
const path = computed(() => modules.value.flatMap((row, y) => row.flatMap((dark, x) => dark ? [`M${x + 4} ${y + 4}h1v1h-1z`] : [])).join(''))
</script>

<template>
    <div class="asset-qr-code inline-flex flex-col items-center bg-white p-2 text-center">
        <svg :viewBox="`0 0 ${size + 8} ${size + 8}`" role="img" :aria-label="`QR code aset ${value}`" class="h-36 w-36 max-w-full" shape-rendering="crispEdges">
            <rect width="100%" height="100%" fill="#fff" />
            <path :d="path" fill="#111827" />
        </svg>
        <span v-if="showLabel" class="mt-1 font-mono text-xs font-semibold tracking-wider text-gray-900">{{ value }}</span>
    </div>
</template>
