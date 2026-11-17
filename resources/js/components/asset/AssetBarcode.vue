<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ value: string }>()

const patterns: Record<string, string> = {
    '0': 'nnnwwnwnn', '1': 'wnnwnnnnw', '2': 'nnwwnnnnw', '3': 'wnwwnnnnn',
    '4': 'nnnwwnnnw', '5': 'wnnwwnnnn', '6': 'nnwwwnnnn', '7': 'nnnwnnwnw',
    '8': 'wnnwnnwnn', '9': 'nnwwnnwnn', 'A': 'wnnnnwnnw', 'B': 'nnwnnwnnw',
    'C': 'wnwnnwnnn', 'D': 'nnnnwwnnw', 'E': 'wnnnwwnnn', 'F': 'nnwnwwnnn',
    'G': 'nnnnnwwnw', 'H': 'wnnnnwwnn', 'I': 'nnwnnwwnn', 'J': 'nnnnwwwnn',
    'K': 'wnnnnnnww', 'L': 'nnwnnnnww', 'M': 'wnwnnnnwn', 'N': 'nnnnwnnww',
    'O': 'wnnnwnnwn', 'P': 'nnwnwnnwn', 'Q': 'nnnnnnwww', 'R': 'wnnnnnwwn',
    'S': 'nnwnnnwwn', 'T': 'nnnnwnwwn', 'U': 'wwnnnnnnw', 'V': 'nwwnnnnnw',
    'W': 'wwwnnnnnn', 'X': 'nwnnwnnnw', 'Y': 'wwnnwnnnn', 'Z': 'nwwnwnnnn',
    '-': 'nwnnnnwnw', '.': 'wwnnnnwnn', ' ': 'nwwnnnwnn', '$': 'nwnwnwnnn',
    '/': 'nwnwnnnwn', '+': 'nwnnnwnwn', '%': 'nnnwnwnwn', '*': 'nwnnwnwnn',
}

const bars = computed(() => {
    const characters = `*${props.value.toUpperCase()}*`
    const rectangles: { x: number; width: number }[] = []
    let position = 10

    for (const character of characters) {
        const pattern = patterns[character]
        if (!pattern) {
            throw new Error(`Karakter ${character} tidak didukung oleh barcode Code 39.`)
        }

        pattern.split('').forEach((width, index) => {
            const moduleWidth = width === 'w' ? 3 : 1
            if (index % 2 === 0) {
                rectangles.push({ x: position, width: moduleWidth })
            }
            position += moduleWidth
        })
        position += 1
    }

    return { rectangles, width: position + 9 }
})
</script>

<template>
    <svg :viewBox="`0 0 ${bars.width} 24`" role="img" :aria-label="`Barcode aset ${value}`" class="asset-barcode block h-8 w-full" preserveAspectRatio="none" shape-rendering="crispEdges">
        <rect width="100%" height="100%" fill="#fff" />
        <rect v-for="(bar, index) in bars.rectangles" :key="index" :x="bar.x" y="1" :width="bar.width" height="18" fill="#111827" />
    </svg>
</template>
