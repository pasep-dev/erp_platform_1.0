<script setup lang="ts">
import { computed, type Component } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import {
    Boxes,
    ChevronRight,
    ClipboardCheck,
    Laptop,
    Plus,
    ShoppingCart,
    TriangleAlert,
    UserCog,
    Wrench,
} from 'lucide-vue-next'
import { route } from 'ziggy-js'
import AppLayout from '@/layout/AppLayout.vue'

defineOptions({
    layout: AppLayout,
})

/* ------------------------------------------------------------------ */
/* Types                                                               */
/* ------------------------------------------------------------------ */
type ModuleKey = 'approval' | 'procurement' | 'asset' | 'inventory' | 'admin'
type Severity = 'critical' | 'warning' | 'info'
type StatKey =
    | 'pendingApprovals'
    | 'openPurchaseOrders'
    | 'totalAssets'
    | 'inMaintenance'
    | 'lowStock'
    | 'openIncidents'

interface Approval {
    id: number | string
    type: string
    title: string
    requester: string
    age: string
    routeName?: string
}
interface Alert {
    module: ModuleKey
    severity: Severity
    text: string
    routeName: string
}
interface Activity {
    text: string
    by: string
    time: string
}
interface AssetStatus {
    label: string
    value: number
    color: string
}
interface PipelineItem {
    label: string
    value: number
}
interface DepartmentOption {
    id: string
    code: string
    name: string
}

const props = defineProps<{
    stats: Record<StatKey, number>
    access: Record<ModuleKey, boolean>
    approvals: Approval[]
    alerts: Alert[]
    activities: Activity[]
    assetStatus: AssetStatus[]
    pipeline: PipelineItem[]
    departments: DepartmentOption[]
    selectedDepartmentId: string | null
}>()

const page = usePage()

/* ------------------------------------------------------------------ */
/* Role access                                                         */
/* ------------------------------------------------------------------ */
const user = computed(() => (page.props as any).auth?.user ?? null)

// Accepts ['admin'] or [{ name: 'admin' }]. Falls back to super-admin while
// the auth props are not wired up, so the page is fully visible in development.
const roles = computed<string[]>(() => {
    const raw = user.value?.roles
    if (!Array.isArray(raw) || raw.length === 0) return ['super-admin']
    return raw.map((r: string | { name: string }) =>
        typeof r === 'string' ? r : r.name,
    )
})

const canSee = (module: ModuleKey): boolean => props.access[module]
const selectedDepartment = computed(() =>
    props.departments.find((department) => department.id === props.selectedDepartmentId) ?? null,
)

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */
// Replace with your own route helper (Ziggy / Wayfinder) if it differs.
const href = (name?: string): string => {
    if (!name) return '#'
    try {
        return (window as any).route?.(name) ?? '#'
    } catch {
        return '#'
    }
}

const fmt = (n: number): string => new Intl.NumberFormat('en-US').format(n)

const hour = new Date().getHours()
const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening'
const today = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
})
const firstName = computed(() => user.value?.name?.split(' ')[0] ?? 'there')

const panel =
    'app-card overflow-hidden'
const link =
    'text-sm font-medium text-brand-700 hover:underline dark:text-brand-400'

const s = computed(() => props.stats)
const approvals = computed(() => props.approvals)
const alerts = computed(() => props.alerts.filter((a) => canSee(a.module)))
const activities = computed(() => props.activities)

/* ------------------------------------------------------------------ */
/* Configuration                                                       */
/* ------------------------------------------------------------------ */
const quickDefs = [
    { module: 'procurement', label: 'Create purchase request', routeName: 'transaction.procurement.purchase-requests.index' },
    { module: 'asset', label: 'Register asset', routeName: 'transaction.asset-management.registrations.index' },
    { module: 'inventory', label: 'Request items', routeName: 'transaction.inventory.requests.index' },
] as const
const quick = computed(() => quickDefs.filter((q) => canSee(q.module)).slice(0, 3))

const statDefs: {
    module: ModuleKey
    key: StatKey
    label: string
    hint: string
    routeName: string
    icon: Component
    tone: 'primary' | 'info' | 'success' | 'warning' | 'danger'
}[] = [
    { module: 'approval', key: 'pendingApprovals', label: 'Pending approvals', hint: 'Waiting for you', routeName: 'approval.inbox.index', icon: ClipboardCheck, tone: 'primary' },
    { module: 'procurement', key: 'openPurchaseOrders', label: 'Open purchase orders', hint: 'Not fully received', routeName: 'transaction.procurement.purchase-orders.index', icon: ShoppingCart, tone: 'info' },
    { module: 'asset', key: 'totalAssets', label: 'Registered assets', hint: 'Across all locations', routeName: 'transaction.asset-management.directory.index', icon: Laptop, tone: 'success' },
    { module: 'asset', key: 'inMaintenance', label: 'In maintenance', hint: 'Currently out of use', routeName: 'transaction.asset-management.maintenance.index', icon: Wrench, tone: 'warning' },
    { module: 'inventory', key: 'lowStock', label: 'Below minimum stock', hint: 'Needs restocking', routeName: 'transaction.inventory.stocks.index', icon: Boxes, tone: 'danger' },
    { module: 'asset', key: 'openIncidents', label: 'Open incidents', hint: 'Not yet resolved', routeName: 'transaction.asset-management.incidents.index', icon: TriangleAlert, tone: 'danger' },
]
const statCells = computed(() => statDefs.filter((d) => canSee(d.module)))

const modules: {
    key: ModuleKey
    title: string
    description: string
    icon: Component
    links: { label: string; routeName: string }[]
}[] = [
    {
        key: 'approval',
        title: 'My Approval',
        description: 'Review requests that reached your approval step.',
        icon: ClipboardCheck,
        links: [
            { label: 'Inbox', routeName: 'approval.inbox.index' },
            { label: 'History', routeName: 'approval.history.index' },
        ],
    },
    {
        key: 'procurement',
        title: 'Procurement',
        description: 'Request, order, and receive goods.',
        icon: ShoppingCart,
        links: [
            { label: 'Purchase Request', routeName: 'transaction.procurement.purchase-requests.index' },
            { label: 'Purchase Order', routeName: 'transaction.procurement.purchase-orders.index' },
            { label: 'Goods Receipt', routeName: 'transaction.procurement.goods-receipts.index' },
        ],
    },
    {
        key: 'asset',
        title: 'Asset Management',
        description: 'Register, assign, maintain, and retire assets.',
        icon: Laptop,
        links: [
            { label: 'Registration', routeName: 'transaction.asset-management.registrations.index' },
            { label: 'Asset Directory', routeName: 'transaction.asset-management.directory.index' },
            { label: 'Asset Assignment', routeName: 'transaction.asset-management.assignments.index' },
            { label: 'Maintenance & Service', routeName: 'transaction.asset-management.maintenance.index' },
            { label: 'Audit / Stocktake', routeName: 'transaction.asset-management.audits.index' },
            { label: 'Asset Incidents', routeName: 'transaction.asset-management.incidents.index' },
            { label: 'Asset Transfers', routeName: 'transaction.asset-management.transfers.index' },
            { label: 'Asset Disposals', routeName: 'transaction.asset-management.disposals.index' },
        ],
    },
    {
        key: 'inventory',
        title: 'Inventory Management',
        description: 'Track consumables, spare parts, and stock movement.',
        icon: Boxes,
        links: [
            { label: 'Stock Overview', routeName: 'transaction.inventory.index' },
            { label: 'Receipts from GR', routeName: 'transaction.inventory.receipts.index' },
            { label: 'Stock Items', routeName: 'transaction.inventory.stocks.index' },
            { label: 'Item Requests', routeName: 'transaction.inventory.requests.index' },
            { label: 'Stock Transactions', routeName: 'transaction.inventory.transactions.index' },
            { label: 'Stocktake', routeName: 'transaction.inventory.stocktakes.index' },
        ],
    },
    {
        key: 'admin',
        title: 'Administration',
        description: 'Configure how requests are approved.',
        icon: UserCog,
        links: [{ label: 'Approval Setting', routeName: 'administrator.approval-workflows.index' }],
    },
]
const visibleModules = computed(() => modules.filter((m) => canSee(m.key)))

const assetStatus = computed(() => props.assetStatus)
const assetTotal = computed(() => assetStatus.value.reduce((sum, item) => sum + item.value, 0))
const pipeline = computed(() => props.pipeline)
const pipelineMax = computed(() => Math.max(...pipeline.value.map((item) => item.value), 1))

const dot: Record<Severity, string> = {
    critical: 'bg-rose-500',
    warning: 'bg-amber-500',
    info: 'bg-info-500',
}
</script>

<template>
    <Head :title="page.props.app.name" />

    <div class="space-y-6">
        <!-- Header -->
        <header class="app-page-header flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="app-page-title text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    {{ greeting }}, {{ firstName }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ today }}</p>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span
                        v-for="r in roles"
                        :key="r"
                        class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ r }}
                    </span>
                </div>
            </div>

            <div v-if="quick.length" class="flex flex-wrap gap-2">
                <Link
                    v-for="(q, i) in quick"
                    :key="q.label"
                    :href="href(q.routeName)"
                    class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                    :class="
                        i === 0
                            ? 'bg-brand-700 text-white hover:bg-brand-800'
                            : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800'
                    "
                >
                    <Plus class="h-4 w-4" />
                    {{ q.label }}
                </Link>
            </div>
        </header>

        <nav
            v-if="props.departments.length > 1"
            aria-label="Filter dashboard berdasarkan department"
            role="tablist"
            class="flex flex-wrap gap-2 border-b border-gray-200 pb-3 dark:border-gray-800"
        >
            <Link
                v-for="department in props.departments"
                :key="department.id"
                :href="route('dashboard', { department_id: department.id })"
                role="tab"
                :aria-selected="department.id === props.selectedDepartmentId"
                class="rounded-lg px-4 py-2 text-sm font-medium transition"
                :class="department.id === props.selectedDepartmentId
                    ? 'bg-brand-700 text-white'
                    : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'"
            >
                {{ department.code }} · {{ department.name }}
            </Link>
        </nav>
        <div
            v-else-if="selectedDepartment"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800 dark:bg-brand-500/10 dark:text-brand-200"
        >
            <span class="font-medium">Department:</span>
            <span>{{ selectedDepartment.code }} · {{ selectedDepartment.name }}</span>
        </div>

        <!-- Key figures -->
        <section v-if="statCells.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="c in statCells"
                    :key="c.key"
                    :href="href(c.routeName)"
                    class="app-card flex items-start justify-between gap-4 transition-transform duration-200 hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ c.label }}</p>
                        <p class="mt-3 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ fmt(s[c.key]) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ c.hint }}</p>
                    </div>
                    <span class="dashboard-stat-icon" :class="`dashboard-stat-icon--${c.tone}`">
                        <component :is="c.icon" class="h-5 w-5" />
                    </span>
                </Link>
        </section>

        <!-- Work queue + attention -->
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <!-- Approvals -->
                <section :class="[panel, 'p-0!']">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                                Waiting for your approval
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ s.pendingApprovals }} {{ s.pendingApprovals === 1 ? 'request' : 'requests' }}
                            </p>
                        </div>
                        <Link :href="href('approval.inbox.index')" :class="link">Open inbox</Link>
                    </div>

                    <ul v-if="approvals.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="a in approvals.slice(0, 5)" :key="a.id" class="flex items-center gap-4 px-5 py-3.5">
                            <span
                                class="hidden w-36 shrink-0 truncate rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 sm:inline-block dark:bg-gray-800 dark:text-gray-300"
                            >
                                {{ a.type }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ a.title }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Requested by {{ a.requester }}, {{ a.age }}
                                </p>
                            </div>
                            <Link :href="href(a.routeName ?? 'approval.inbox.index')" :class="link">Review</Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nothing is waiting for you. New requests appear here when they reach your step.
                    </p>
                </section>

                <!-- Overview -->
                <div v-if="canSee('asset') || canSee('procurement')" class="grid gap-6 md:grid-cols-2">
                    <section v-if="canSee('asset')" :class="panel">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Asset status</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ fmt(assetTotal) }} registered assets</p>

                        <div class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div
                                v-for="i in assetStatus"
                                :key="i.label"
                                :class="i.color"
                                :style="{ width: assetTotal > 0 ? (i.value / assetTotal) * 100 + '%' : '0%' }"
                            />
                        </div>
                        <ul class="mt-4 space-y-2 text-sm">
                            <li v-for="i in assetStatus" :key="i.label" class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <span class="h-2 w-2 rounded-full" :class="i.color" />
                                    {{ i.label }}
                                </span>
                                <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ fmt(i.value) }}</span>
                            </li>
                        </ul>
                    </section>

                    <section v-if="canSee('procurement')" :class="panel">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Procurement pipeline</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">From request to goods receipt</p>

                        <ul class="mt-4 space-y-4">
                            <li v-for="p in pipeline" :key="p.label">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-300">{{ p.label }}</span>
                                    <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ p.value }}</span>
                                </div>
                                <div class="mt-1.5 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                    <div
                                        class="h-2 rounded-full bg-brand-700"
                                        :style="{ width: (p.value / pipelineMax) * 100 + '%' }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <!-- Right column -->
            <div class="space-y-6">
                <section :class="[panel, 'p-0!']">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Needs attention</h2>
                    </div>
                    <ul v-if="alerts.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="a in alerts" :key="a.text">
                            <Link
                                :href="href(a.routeName)"
                                class="flex items-start gap-3 px-5 py-3.5 text-sm text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800/50"
                            >
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="dot[a.severity]" />
                                {{ a.text }}
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        All clear. Nothing is overdue or running low.
                    </p>
                </section>

                <section :class="[panel, 'p-0!']">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recent activity</h2>
                    </div>
                    <ol class="relative space-y-5 px-5 py-5">
                        <span class="absolute bottom-6 left-6.25 top-6 w-px bg-gray-200 dark:bg-gray-800" />
                        <li v-for="a in activities" :key="a.text" class="relative flex gap-3">
                            <span
                                class="relative z-10 mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full border-2 border-white bg-gray-400 dark:border-gray-900"
                            />
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ a.text }}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">{{ a.by }}, {{ a.time }}</p>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>
        </div>

        <!-- Modules -->
        <section v-if="visibleModules.length">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Your modules</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Shown according to your role.</p>

            <div class="mt-5 grid gap-x-10 gap-y-9 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="m in visibleModules" :key="m.key">
                    <div class="flex items-start gap-3 border-b border-gray-200 pb-3 dark:border-gray-800">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                        >
                            <component :is="m.icon" class="h-4.5 w-4.5" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ m.title }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ m.description }}</p>
                        </div>
                    </div>
                    <ul>
                        <li v-for="l in m.links" :key="l.routeName">
                            <Link
                                :href="href(l.routeName)"
                                class="flex items-center justify-between border-b border-gray-100 py-2.5 text-sm text-gray-700 transition hover:text-brand-700 dark:border-gray-800 dark:text-gray-300 dark:hover:text-brand-400"
                            >
                                {{ l.label }}
                                <ChevronRight class="h-4 w-4 text-gray-300 dark:text-gray-600" />
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.dashboard-stat-icon {
    display: inline-flex;
    width: 2.75rem;
    height: 2.75rem;
    flex: 0 0 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: var(--app-radius-control);
}

.dashboard-stat-icon--primary {
    color: var(--p-primary-color);
    background: var(--app-primary-soft);
}

.dashboard-stat-icon--info {
    color: var(--app-info-strong);
    background: var(--app-info-soft);
}

.dashboard-stat-icon--success {
    color: var(--app-success-strong);
    background: var(--app-success-soft);
}

.dashboard-stat-icon--warning {
    color: var(--app-warning-strong);
    background: var(--app-warning-soft);
}

.dashboard-stat-icon--danger {
    color: var(--app-danger-strong);
    background: var(--app-danger-soft);
}
</style>
