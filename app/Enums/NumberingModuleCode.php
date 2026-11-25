<?php

namespace App\Enums;

enum NumberingModuleCode: string
{
    case Vendor = 'VENDOR';
    case VendorType = 'VENDOR_TYPE';
    case Product = 'PRODUCT';
    case ProductType = 'PRODUCT_TYPE';
    case ProductBrand = 'PRODUCT_BRAND';
    case Asset = 'ASSET';
    case AssetAssignment = 'ASSET_ASSIGNMENT';
    case AssetMaintenance = 'ASSET_MAINTENANCE';
    case AssetAudit = 'ASSET_AUDIT';
    case AssetIncident = 'ASSET_INCIDENT';
    case AssetTransfer = 'ASSET_TRANSFER';
    case AssetDisposal = 'ASSET_DISPOSAL';
    case PurchaseRequest = 'PURCHASE_REQUEST';
    case PurchaseOrder = 'PURCHASE_ORDER';
    case GoodsReceipt = 'GOODS_RECEIPT';
    case InventoryReceipt = 'INVENTORY_RECEIPT';
    case InventoryIssue = 'INVENTORY_ISSUE';
    case InventoryReturn = 'INVENTORY_RETURN';
    case InventoryAdjustment = 'INVENTORY_ADJUSTMENT';
    case InventoryRequest = 'INVENTORY_REQUEST';
    case InventoryStocktake = 'INVENTORY_STOCKTAKE';

    /**
     * @return array{code: string, name: string, category: string}
     */
    public function moduleDetails(): array
    {
        return [
            'code' => $this->value,
            'name' => match ($this) {
                self::Vendor => 'Vendor',
                self::VendorType => 'Vendor Type',
                self::Product => 'Product Master',
                self::ProductType => 'Product Type',
                self::ProductBrand => 'Product Brand',
                self::Asset => 'Asset Registration',
                self::AssetAssignment => 'Asset Assignment',
                self::AssetMaintenance => 'Asset Maintenance',
                self::AssetAudit => 'Asset Audit',
                self::AssetIncident => 'Asset Incident',
                self::AssetTransfer => 'Asset Transfer',
                self::AssetDisposal => 'Asset Disposal',
                self::PurchaseRequest => 'Purchase Request',
                self::PurchaseOrder => 'Purchase Order',
                self::GoodsReceipt => 'Goods Receipt',
                self::InventoryReceipt => 'Inventory Receipt',
                self::InventoryIssue => 'Inventory Issue',
                self::InventoryReturn => 'Inventory Return',
                self::InventoryAdjustment => 'Inventory Adjustment',
                self::InventoryRequest => 'Inventory Request',
                self::InventoryStocktake => 'Inventory Stocktake',
            },
            'category' => match ($this) {
                self::Vendor, self::VendorType, self::Product, self::ProductType,
                self::ProductBrand => 'Master Data',
                self::Asset, self::AssetAssignment, self::AssetMaintenance,
                self::AssetAudit, self::AssetIncident, self::AssetTransfer,
                self::AssetDisposal => 'Asset Management',
                self::PurchaseRequest, self::PurchaseOrder, self::GoodsReceipt => 'Procurement',
                self::InventoryReceipt, self::InventoryIssue, self::InventoryReturn,
                self::InventoryAdjustment, self::InventoryRequest,
                self::InventoryStocktake => 'Inventory Management',
            },
        ];
    }

    public function previewPermission(): string
    {
        return match ($this) {
            self::Vendor, self::VendorType => 'master.procurement.create',
            self::Product, self::ProductType, self::ProductBrand => 'master.product.create',
            self::Asset => 'registration.create',
            self::AssetAssignment, self::AssetAudit, self::AssetIncident, self::AssetDisposal => 'asset.create',
            self::AssetMaintenance => 'asset.create',
            self::AssetTransfer => 'asset.transfer',
            self::PurchaseRequest => 'purchase-request.create',
            self::PurchaseOrder => 'purchase-order.create',
            self::GoodsReceipt => 'good-receipt.create',
            self::InventoryReceipt, self::InventoryAdjustment, self::InventoryStocktake => 'consumable.update',
            self::InventoryIssue, self::InventoryReturn => 'consumable.issue',
            self::InventoryRequest => 'consumable.request',
        };
    }

    /**
     * @return list<string>
     */
    public function supportedPlaceholders(): array
    {
        $contextPlaceholders = match ($this) {
            self::Vendor, self::VendorType, self::Product, self::ProductType,
            self::ProductBrand => [],
            self::Asset => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'ASSET_TYPE',
                'CATEGORY', 'PRODUCT', 'DOCUMENT_TYPE',
            ],
            self::AssetAssignment => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'ASSET_TYPE',
                'CATEGORY', 'PRODUCT', 'DOCUMENT_TYPE', 'EVENT_TYPE',
            ],
            self::AssetMaintenance => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'ASSET_TYPE',
                'CATEGORY', 'PRODUCT', 'DOCUMENT_TYPE',
            ],
            self::AssetAudit => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'CATEGORY',
                'DOCUMENT_TYPE',
            ],
            self::AssetIncident, self::AssetDisposal => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'ASSET_TYPE',
                'CATEGORY', 'PRODUCT', 'DOCUMENT_TYPE', 'EVENT_TYPE',
            ],
            self::AssetTransfer => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'ASSET_TYPE',
                'CATEGORY', 'PRODUCT', 'DOCUMENT_TYPE', 'SOURCE_COMPANY',
                'SOURCE_DEPARTMENT', 'SOURCE_BRANCH', 'SOURCE_LOCATION',
                'TARGET_COMPANY', 'TARGET_DEPARTMENT', 'TARGET_BRANCH',
                'TARGET_LOCATION',
            ],
            self::PurchaseRequest => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'DOCUMENT_TYPE',
            ],
            self::PurchaseOrder => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'DOCUMENT_TYPE', 'VENDOR',
            ],
            self::GoodsReceipt => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'DOCUMENT_TYPE',
                'VENDOR',
            ],
            self::InventoryReceipt => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'DOCUMENT_TYPE',
                'DIRECTION',
            ],
            self::InventoryIssue => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'DOCUMENT_TYPE',
                'DIRECTION', 'SOURCE_COMPANY', 'SOURCE_DEPARTMENT',
                'SOURCE_BRANCH', 'SOURCE_LOCATION',
            ],
            self::InventoryReturn => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'DOCUMENT_TYPE',
                'DIRECTION', 'TARGET_COMPANY', 'TARGET_DEPARTMENT',
                'TARGET_BRANCH', 'TARGET_LOCATION',
            ],
            self::InventoryAdjustment => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'DOCUMENT_TYPE',
                'DIRECTION', 'SOURCE_COMPANY', 'SOURCE_DEPARTMENT', 'SOURCE_BRANCH',
                'SOURCE_LOCATION', 'TARGET_COMPANY', 'TARGET_DEPARTMENT',
                'TARGET_BRANCH', 'TARGET_LOCATION',
            ],
            self::InventoryRequest => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'DOCUMENT_TYPE',
            ],
            self::InventoryStocktake => [
                'COMPANY', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'DOCUMENT_TYPE',
            ],
        };

        return array_values(array_unique([
            'YYYY', 'YY', 'MM', 'DD', 'SEQ', ...$contextPlaceholders,
        ]));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $module): string => $module->value,
            self::cases(),
        );
    }
}
