import {
    Building2,
    LayoutDashboard,
    Settings,
} from 'lucide-vue-next'

import type { MenuItem } from '../types/menu'

export const menuItems: MenuItem[] = [
    {
        id: 'dashboard',
        label: 'Dashboard',
        routeName: 'dashboard',
        icon: LayoutDashboard,
    },
    {
        id: 'master-data',
        label: 'Master Data',
        icon: Building2,
        children: [
            {
                id: 'organization',
                label: 'Organization',
                children: [
                    {
                        id: 'companies',
                        label: 'Companies',
                        routeName: 'master.organization.companies.index',
                        icon: Building2,
                    },
                    {
                        id: 'branches',
                        label: 'Branches',
                        routeName: 'master.organization.branches.index',
                        icon: Building2,
                    },
                    {
                        id: 'departments',
                        label: 'Departments',
                        routeName: 'master.organization.departments.index',
                        icon: Building2,
                    },
                    {
                        id: 'locations',
                        label: 'Locations',
                        routeName: 'master.organization.locations.index',
                        icon: Building2,
                    },
                    {
                        id: 'costcenter',
                        label: 'Cost Center',
                        routeName: 'master.organization.cost-centers.index',
                        icon: Building2,
                    },
                ],

            },
            {
                id: 'asset-management',
                label: 'Asset Management',
                children: [
                    {
                        id: 'categories',
                        label: 'Categories',
                        routeName: 'master.asset-management.categories.index',
                        icon: Building2,
                    },
                    {
                        id: 'types',
                        label: 'Types',
                        routeName: 'master.asset-management.types.index',
                        icon: Building2,
                    },
                    {
                        id: 'brands',
                        label: 'Brands',
                        routeName: 'master.asset-management.brands.index',
                        icon: Building2,
                    },
                    {
                        id: 'models',
                        label: 'Models',
                        routeName: 'master.asset-management.models.index',
                        icon: Building2,
                    },
                    {
                        id: 'license-types',
                        label: 'License Types',
                        routeName: 'master.asset-management.license-types.index',
                        icon: Building2,
                    },
                ],
            },
            {
                id: 'procurement',
                label: 'Procurement',
                children: [
                    {
                        id: 'vendor-types',
                        label: 'Vendor Types',
                        routeName: 'master.procurement.vendor-types.index',
                        icon: Building2,
                    },
                    {
                        id: 'vendors',
                        label: 'Vendors',
                        routeName: 'master.procurement.vendors.index',
                        icon: Building2,
                    },
                    {
                        id: 'units-of-measure',
                        label: 'Unit of Measure',
                        routeName: 'master.procurement.units-of-measure.index',
                        icon: Building2,
                    },
                    {
                        id: 'payment-terms',
                        label: 'Payment Terms',
                        routeName: 'master.procurement.payment-terms.index',
                        icon: Building2,
                    },
                    {
                        id: 'taxes',
                        label: 'Tax',
                        routeName: 'master.procurement.taxes.index',
                        icon: Building2,
                    },
                    {
                        id: 'procurement-channels',
                        label: 'Procurement Channels',
                        routeName: 'master.procurement.procurement-channels.index',
                        icon: Building2,
                    },
                ],
            },
        ],
    },
    {
        id: 'procurement',
        label: 'Procurement',
        icon: Building2,
        children: [
            {
                id: 'purchase-request',
                label: 'Purchase Request',
                routeName: 'operational.procurement.purchase-request.index',
                icon: Building2,
            },
            {
                id: 'purchase-order',
                label: 'Purchase Order',
                routeName: 'operational.procurement.purchase-request.index',
                icon: Building2,
            },
            {
                id: 'goods-receive',
                label: 'Goods Receipt',
                routeName: 'operational.procurement.purchase-request.index',
                icon: Building2,
            },

            // {
            //     id: 'asset-management',
            //     label: 'Asset Management',
            //     children: [
            //         {
            //             id: 'categories',
            //             label: 'Categories',
            //             routeName: 'master.asset-management.categories.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'types',
            //             label: 'Types',
            //             routeName: 'master.asset-management.types.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'brands',
            //             label: 'Brands',
            //             routeName: 'master.asset-management.brands.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'models',
            //             label: 'Models',
            //             routeName: 'master.asset-management.models.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'license-types',
            //             label: 'License Types',
            //             routeName: 'master.asset-management.license-types.index',
            //             icon: Building2,
            //         },
            //     ],
            // },
            // {
            //     id: 'procurement',
            //     label: 'Procurement',
            //     children: [
            //         {
            //             id: 'vendor-types',
            //             label: 'Vendor Types',
            //             routeName: 'master.procurement.vendor-types.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'vendors',
            //             label: 'Vendors',
            //             routeName: 'master.procurement.vendors.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'units-of-measure',
            //             label: 'Unit of Measure',
            //             routeName: 'master.procurement.units-of-measure.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'payment-terms',
            //             label: 'Payment Terms',
            //             routeName: 'master.procurement.payment-terms.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'taxes',
            //             label: 'Tax',
            //             routeName: 'master.procurement.taxes.index',
            //             icon: Building2,
            //         },
            //         {
            //             id: 'procurement-channels',
            //             label: 'Procurement Channels',
            //             routeName: 'master.procurement.procurement-channels.index',
            //             icon: Building2,
            //         },
            //     ],
            // },
        ],
    },
    {
        id: 'settings',
        label: 'Settings',
        routeName: 'settings.edit',
        icon: Settings,
    },
]