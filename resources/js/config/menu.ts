import {
    Boxes,
    Building2,
    ClipboardCheck,
    Database,
    History,
    KeyRound,
    Laptop,
    LayoutDashboard,
    ShoppingCart,
    Settings,
    UserCog,
} from "lucide-vue-next";

import type { MenuGroup, MenuItem } from "../types/menu";

export const menuItems: MenuItem[] = [
    {
        id: "dashboard",
        label: "Dashboard",
        routeName: "dashboard",
        icon: LayoutDashboard,
        permission: "dashboard.view",
    },
    {
        id: "administrator",
        label: "Administrator",
        icon: KeyRound,
        children: [
            {
                id: "approval-workflows",
                label: "Workflow Approval",
                routeName: "administrator.approval-workflows.index",
                permission: "approval.workflow.view",
            },
            {
                id: "document-numbering",
                label: "Document Numbering",
                routeName: "administrator.numbering-documents.index",
                permission: "numbering.document.view",
            },
            {
                id: "menu-control",
                label: "Menu Control",
                routeName: "administrator.menu-control.index",
                permission: "roles.update",
                administratorOnly: true,
            },
        ],
    },
    {
        id: "master-data",
        label: "Master Data",
        icon: Database,
        children: [
            {
                id: "organization",
                label: "Organization",
                children: [
                    {
                        id: "companies",
                        label: "Companies",
                        routeName: "master.organization.companies.index",
                        icon: Building2,
                        permission: "master.organization.view",
                    },
                    {
                        id: "branches",
                        label: "Branches",
                        routeName: "master.organization.branches.index",
                        icon: Building2,
                        permission: "master.organization.view",
                    },
                    {
                        id: "departments",
                        label: "Departments",
                        routeName: "master.organization.departments.index",
                        icon: Building2,
                        permission: "master.organization.view",
                    },
                    {
                        id: "locations",
                        label: "Locations",
                        routeName: "master.organization.locations.index",
                        icon: Building2,
                        permission: "master.organization.view",
                    },
                    {
                        id: "costcenter",
                        label: "Cost Center",
                        routeName: "master.organization.cost-centers.index",
                        icon: Building2,
                        permission: "master.organization.view",
                    },
                ],
            },
            {
                id: "product",
                label: "Product",
                children: [
                    {
                        id: "categories",
                        label: "Categories",
                        routeName: "master.product.categories.index",
                        icon: Building2,
                        permission: "master.product.view",
                    },
                    {
                        id: "types",
                        label: "Types",
                        routeName: "master.product.types.index",
                        icon: Building2,
                        permission: "master.product.view",
                    },
                    {
                        id: "brands",
                        label: "Brands",
                        routeName: "master.product.brands.index",
                        icon: Building2,
                        permission: "master.product.view",
                    },
                    {
                        id: "models",
                        label: "Models",
                        routeName: "master.product.models.index",
                        icon: Building2,
                        permission: "master.product.view",
                    },
                    {
                        id: "license-types",
                        label: "License Types",
                        routeName: "master.product.license-types.index",
                        icon: Building2,
                        permission: "master.product.view",
                    },
                    {
                        id: "products",
                        label: "Products",
                        routeName: "master.product.products.index",
                        icon: Building2,
                        permission: "master.product.view",
                    },
                ],
            },
            {
                id: "procurement-master",
                label: "Procurement",
                children: [
                    {
                        id: "vendor-types",
                        label: "Vendor Types",
                        routeName: "master.procurement.vendor-types.index",
                        icon: Building2,
                        permission: "master.procurement.view",
                    },
                    {
                        id: "vendors",
                        label: "Vendors",
                        routeName: "master.procurement.vendors.index",
                        icon: Building2,
                        permission: "master.procurement.view",
                    },
                    {
                        id: "units-of-measure",
                        label: "Unit of Measure",
                        routeName: "master.procurement.units-of-measure.index",
                        icon: Building2,
                        permission: "master.procurement.view",
                    },
                    {
                        id: "payment-terms",
                        label: "Payment Terms",
                        routeName: "master.procurement.payment-terms.index",
                        icon: Building2,
                        permission: "master.procurement.view",
                    },
                    {
                        id: "taxes",
                        label: "Tax",
                        routeName: "master.procurement.taxes.index",
                        icon: Building2,
                        permission: "master.procurement.view",
                    },
                ],
            },
        ],
    },
    {
        id: "my-approval",
        label: "My Approval",
        icon: UserCog,
        children: [
            {
                id: "inbox",
                label: "Inbox",
                routeName: "approval.inbox.index",
                permission: "approval.view",
            },
            {
                id: "history",
                label: "History",
                routeName: "approval.history.index",
                permission: "approval.view",
            },
        ],
    },
    {
        id: "procurement",
        label: "Procurement",
        icon: ShoppingCart,
        children: [
            {
                id: "purchase-request",
                label: "Purchase Request",
                routeName: "transaction.procurement.purchase-requests.index",
                icon: Building2,
                permission: "purchase-request.view",
            },
            {
                id: "purchase-order",
                label: "Purchase Order",
                routeName: "transaction.procurement.purchase-orders.index",
                icon: Building2,
                permission: "purchase-order.view",
            },
            {
                id: "goods-receipt",
                label: "Goods Receipt",
                routeName: "transaction.procurement.goods-receipts.index",
                icon: Building2,
                permission: "good-receipt.view",
            },
        ],
    },

    {
        id: "asset-management",
        label: "Asset Management",
        icon: Laptop,
        children: [
            {
                id: "registration",
                label: "Registration",
                routeName: "transaction.asset-management.registrations.index",
                icon: Building2,
                permission: "registration.view",
            },
            {
                id: "asset-directory",
                label: "Asset Directory",
                routeName: "transaction.asset-management.directory.index",
                icon: Building2,
                permission: "asset.view",
            },
            {
                id: "asset-assignment",
                label: "Asset Assignment",
                routeName: "transaction.asset-management.assignments.index",
                icon: Building2,
                permission: "asset.view",
            },
            {
                id: "asset-maintenance",
                label: "Maintenance & Service",
                routeName: "transaction.asset-management.maintenance.index",
                permission: "asset.view",
            },
            {
                id: "asset-audit",
                label: "Audit / Stocktake",
                routeName: "transaction.asset-management.audits.index",
                permission: "asset.view",
            },
            {
                id: "asset-incidents",
                label: "Asset Incidents",
                routeName: "transaction.asset-management.incidents.index",
                permission: "asset.view",
            },
            {
                id: "asset-transfers",
                label: "Asset Transfers",
                routeName: "transaction.asset-management.transfers.index",
                icon: Building2,
                permission: "asset.view",
            },
            {
                id: "asset-disposals",
                label: "Asset Disposals",
                routeName: "transaction.asset-management.disposals.index",
                icon: Building2,
                permission: "asset.view",
            },
        ],
    },

    {
        id: "user-management",
        label: "User Management",
        icon: UserCog,
        children: [
            {
                id: "user-accounts",
                label: "User Account",
                routeName: "administrator.users.index",
                permission: "users.view",
            },
            {
                id: "roles",
                label: "Role",
                routeName: "administrator.roles.index",
                permission: "roles.view",
            },
            {
                id: "access-control",
                label: "Access Control",
                routeName: "administrator.access-control.index",
                permission: "roles.update",
                administratorOnly: true,
            },
        ],
    },

    {
        id: "inventory",
        label: "Inventory Management",
        icon: Boxes,
        children: [
            {
                id: "inventory-overview",
                label: "Stock Overview",
                routeName: "transaction.inventory.index",
                exactRouteMatch: true,
                icon: LayoutDashboard,
                permission: "consumable.view",
            },
            {
                id: "inventory-receipts",
                label: "Receipts from GR",
                routeName: "transaction.inventory.receipts.index",
                icon: ClipboardCheck,
                permission: "consumable.view",
            },
            {
                id: "inventory-stocks",
                label: "Stock Items",
                routeName: "transaction.inventory.stocks.index",
                icon: Building2,
                permission: "consumable.view",
            },
            {
                id: "inventory-requests",
                label: "Item Requests",
                routeName: "transaction.inventory.requests.index",
                icon: ClipboardCheck,
                permission: "consumable.request",
            },

            {
                id: "inventory-transactions",
                label: "Stock Transactions",
                routeName: "transaction.inventory.transactions.index",
                icon: Building2,
                permission: "consumable.view",
            },
            {
                id: "inventory-stocktakes",
                label: "Stocktake",
                routeName: "transaction.inventory.stocktakes.index",
                icon: ClipboardCheck,
                permission: "consumable.view",
            },
        ],
    },
    {
        id: "settings",
        label: "Settings",
        routeName: "settings.edit",
        icon: Settings,
        permission: "system.settings.view",
    },
    {
        id: "activity-logs",
        label: "Activity Logs",
        routeName: "administrator.activity-logs.index",
        icon: History,
        permission: "activity-logs.view",
    },
];

export const menuGroups: MenuGroup[] = [
    {
        label: "Main Menu",
        items: menuItems.filter(({ id }) =>
            ["dashboard", "master-data"].includes(id),
        ),
    },
    {
        label: "Transactions",
        items: menuItems.filter(({ id }) =>
            [
                "procurement",
                "inventory",
                "asset-management",
                "my-approval",
            ].includes(id),
        ),
    },
    {
        label: "Administration",
        items: menuItems.filter(({ id }) =>
            [
                "administrator",
                "user-management",
                "settings",
                "activity-logs",
            ].includes(id),
        ),
    },
];
