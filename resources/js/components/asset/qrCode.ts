type QrVersion = {
    version: number
    dataCodewords: number
    errorCodewords: number
    alignmentCenter: number | null
}

const versions: QrVersion[] = [
    { version: 1, dataCodewords: 19, errorCodewords: 7, alignmentCenter: null },
    { version: 2, dataCodewords: 34, errorCodewords: 10, alignmentCenter: 18 },
    { version: 3, dataCodewords: 55, errorCodewords: 15, alignmentCenter: 22 },
    { version: 4, dataCodewords: 80, errorCodewords: 20, alignmentCenter: 26 },
    { version: 5, dataCodewords: 108, errorCodewords: 26, alignmentCenter: 30 },
]

function appendBits(bits: number[], value: number, length: number): void {
    for (let index = length - 1; index >= 0; index--) {
        bits.push((value >>> index) & 1)
    }
}

function multiply(left: number, right: number): number {
    let result = 0
    let multiplicand = left
    let multiplier = right

    while (multiplier > 0) {
        if ((multiplier & 1) !== 0) result ^= multiplicand
        multiplier >>>= 1
        multiplicand <<= 1
        if ((multiplicand & 0x100) !== 0) multiplicand ^= 0x11d
    }

    return result
}

function errorCorrection(data: number[], degree: number): number[] {
    let generator = [1]
    let root = 1

    for (let index = 0; index < degree; index++) {
        const next = new Array<number>(generator.length + 1).fill(0)
        generator.forEach((coefficient, position) => {
            next[position] ^= coefficient
            next[position + 1] ^= multiply(coefficient, root)
        })
        generator = next
        root = multiply(root, 2)
    }

    const remainder = new Array<number>(degree).fill(0)
    for (const codeword of data) {
        const factor = codeword ^ remainder[0]
        remainder.shift()
        remainder.push(0)
        for (let index = 0; index < degree; index++) {
            remainder[index] ^= multiply(generator[index + 1], factor)
        }
    }

    return remainder
}

function makeDataCodewords(value: string, capacity: number): number[] | null {
    const bytes = [...new TextEncoder().encode(value)]
    const bits: number[] = []
    appendBits(bits, 0b0100, 4)
    appendBits(bits, bytes.length, 8)
    bytes.forEach((byte) => appendBits(bits, byte, 8))

    if (bits.length > capacity * 8) return null

    appendBits(bits, 0, Math.min(4, capacity * 8 - bits.length))
    while (bits.length % 8 !== 0) bits.push(0)

    const codewords: number[] = []
    for (let index = 0; index < bits.length; index += 8) {
        codewords.push(bits.slice(index, index + 8).reduce((value, bit) => (value << 1) | bit, 0))
    }

    for (let pad = 0; codewords.length < capacity; pad++) {
        codewords.push(pad % 2 === 0 ? 0xec : 0x11)
    }

    return codewords
}

function formatBits(mask: number): number {
    const data = (0b01 << 3) | mask
    let remainder = data << 10

    for (let bit = 14; bit >= 10; bit--) {
        if (((remainder >>> bit) & 1) !== 0) remainder ^= 0x537 << (bit - 10)
    }

    return ((data << 10) | remainder) ^ 0x5412
}

export function createAssetQrCode(value: string): boolean[][] {
    const selected = versions
        .map((configuration) => ({ configuration, data: makeDataCodewords(value, configuration.dataCodewords) }))
        .find((candidate) => candidate.data !== null)

    if (!selected?.data) {
        throw new Error('Kode aset terlalu panjang untuk QR code yang didukung.')
    }

    const { configuration, data } = selected
    const codewords = [...data, ...errorCorrection(data, configuration.errorCodewords)]
    const size = configuration.version * 4 + 17
    const modules: Array<Array<boolean | null>> = Array.from({ length: size }, () => new Array<boolean | null>(size).fill(null))
    const functionModules = Array.from({ length: size }, () => new Array<boolean>(size).fill(false))

    const setFunction = (x: number, y: number, dark: boolean): void => {
        if (x >= 0 && x < size && y >= 0 && y < size) {
            modules[y][x] = dark
            functionModules[y][x] = true
        }
    }

    const drawFinder = (centerX: number, centerY: number): void => {
        for (let dy = -1; dy <= 7; dy++) {
            for (let dx = -1; dx <= 7; dx++) {
                const x = centerX + dx
                const y = centerY + dy
                const inFinder = dx >= 0 && dx <= 6 && dy >= 0 && dy <= 6
                const dark = inFinder && (
                    dx === 0 || dx === 6 || dy === 0 || dy === 6
                    || (dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4)
                )
                setFunction(x, y, dark)
            }
        }
    }

    drawFinder(0, 0)
    drawFinder(size - 7, 0)
    drawFinder(0, size - 7)

    if (configuration.alignmentCenter !== null) {
        const center = configuration.alignmentCenter
        for (let dy = -2; dy <= 2; dy++) {
            for (let dx = -2; dx <= 2; dx++) {
                setFunction(center + dx, center + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1)
            }
        }
    }

    for (let index = 8; index < size - 8; index++) {
        if (modules[6][index] === null) setFunction(index, 6, index % 2 === 0)
        if (modules[index][6] === null) setFunction(6, index, index % 2 === 0)
    }

    const format = formatBits(0)
    for (let index = 0; index <= 5; index++) setFunction(8, index, ((format >>> index) & 1) !== 0)
    setFunction(8, 7, ((format >>> 6) & 1) !== 0)
    setFunction(8, 8, ((format >>> 7) & 1) !== 0)
    setFunction(7, 8, ((format >>> 8) & 1) !== 0)
    for (let index = 9; index < 15; index++) setFunction(14 - index, 8, ((format >>> index) & 1) !== 0)
    for (let index = 0; index < 8; index++) setFunction(size - 1 - index, 8, ((format >>> index) & 1) !== 0)
    for (let index = 8; index < 15; index++) setFunction(8, size - 15 + index, ((format >>> index) & 1) !== 0)
    setFunction(8, size - 8, true)

    const dataBits: number[] = []
    codewords.forEach((codeword) => appendBits(dataBits, codeword, 8))
    let bitIndex = 0
    let upward = true

    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) right--
        for (let vertical = 0; vertical < size; vertical++) {
            const y = upward ? size - 1 - vertical : vertical
            for (let offset = 0; offset < 2; offset++) {
                const x = right - offset
                if (functionModules[y][x]) continue
                const bit = bitIndex < dataBits.length ? dataBits[bitIndex] === 1 : false
                modules[y][x] = bit !== ((x + y) % 2 === 0)
                bitIndex++
            }
        }
        upward = !upward
    }

    return modules.map((row) => row.map((module) => module === true))
}
