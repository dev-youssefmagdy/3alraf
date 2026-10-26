<?php

namespace App\Providers;

use App\Repositories\Contracts\LanguageRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Eloquent\ReportRepository;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Eloquent\LanguageRepository;
use App\Repositories\Eloquent\TenantRepository;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Eloquent\BrandRepository;
use App\Repositories\Contracts\AttributeRepositoryInterface;
use App\Repositories\Eloquent\AttributeRepository;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Contracts\TenantProductRepositoryInterface;
use App\Repositories\Eloquent\TenantProductRepository;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Eloquent\CityRepository;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Eloquent\BranchRepository;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Eloquent\SupplierRepository;
use App\Repositories\Contracts\ShippingCompanyRepositoryInterface;
use App\Repositories\Eloquent\ShippingCompanyRepository;
use App\Repositories\Contracts\PaymentMethodRepositoryInterface;
use App\Repositories\Eloquent\PaymentMethodRepository;
use App\Repositories\Contracts\BannerRepositoryInterface;
use App\Repositories\Eloquent\BannerRepository;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Eloquent\PurchaseOrderRepository;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Repositories\Eloquent\StockTransferRepository;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use App\Repositories\Eloquent\StockAdjustmentRepository;
use App\Repositories\Contracts\StockLevelRepositoryInterface;
use App\Repositories\Eloquent\StockLevelRepository;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Eloquent\StockMovementRepository;
use App\Repositories\Contracts\DiscountCodeRepositoryInterface;
use App\Repositories\Eloquent\DiscountCodeRepository;
use App\Repositories\Contracts\BundleRepositoryInterface;
use App\Repositories\Contracts\PackagingOptionRepositoryInterface;
use App\Repositories\Eloquent\PackagingOptionRepository;
use App\Repositories\Eloquent\BundleRepository;
use App\Repositories\Contracts\FlashSaleRepositoryInterface;
use App\Repositories\Eloquent\FlashSaleRepository;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Contracts\OrderReturnRepositoryInterface;
use App\Repositories\Eloquent\OrderReturnRepository;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use App\Repositories\Eloquent\CustomerAddressRepository;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Contracts\AffiliateRepositoryInterface;
use App\Repositories\Eloquent\AffiliateRepository;
use App\Repositories\Contracts\AffiliateTierRepositoryInterface;
use App\Repositories\Eloquent\AffiliateTierRepository;
use App\Repositories\Contracts\AffiliatePayoutRepositoryInterface;
use App\Repositories\Eloquent\AffiliatePayoutRepository;
use App\Repositories\Contracts\AffiliateCommissionRepositoryInterface;
use App\Repositories\Eloquent\AffiliateCommissionRepository;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Eloquent\ReviewRepository;
use App\Repositories\Contracts\CustomPageRepositoryInterface;
use App\Repositories\Eloquent\CustomPageRepository;
use App\Repositories\Contracts\BlogPostRepositoryInterface;
use App\Repositories\Eloquent\BlogPostRepository;
use App\Repositories\Contracts\StaticPageRepositoryInterface;
use App\Repositories\Eloquent\StaticPageRepository;
use App\Repositories\Contracts\ProductListItemRepositoryInterface;
use App\Repositories\Eloquent\ProductListItemRepository;
use App\Repositories\Contracts\WishlistItemRepositoryInterface;
use App\Repositories\Eloquent\WishlistItemRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** interface => implementation. Add new repositories here. */
    public array $bindings = [
        \App\Repositories\Contracts\AiProviderRepositoryInterface::class => \App\Repositories\Eloquent\AiProviderRepository::class,
        \App\Repositories\Contracts\AiModelRepositoryInterface::class => \App\Repositories\Eloquent\AiModelRepository::class,
        \App\Repositories\Contracts\AiTenantSettingRepositoryInterface::class => \App\Repositories\Eloquent\AiTenantSettingRepository::class,
        AffiliateRepositoryInterface::class => AffiliateRepository::class,
        AffiliateTierRepositoryInterface::class => AffiliateTierRepository::class,
        AffiliatePayoutRepositoryInterface::class => AffiliatePayoutRepository::class,
        AffiliateCommissionRepositoryInterface::class => AffiliateCommissionRepository::class,
        ReviewRepositoryInterface::class => ReviewRepository::class,
        CustomPageRepositoryInterface::class => CustomPageRepository::class,
        StaticPageRepositoryInterface::class => StaticPageRepository::class,
        BlogPostRepositoryInterface::class => BlogPostRepository::class,
        ProductListItemRepositoryInterface::class => ProductListItemRepository::class,
        WishlistItemRepositoryInterface::class => WishlistItemRepository::class,
        LanguageRepositoryInterface::class => LanguageRepository::class,
        TenantRepositoryInterface::class => TenantRepository::class,
        BrandRepositoryInterface::class => BrandRepository::class,
        \App\Repositories\Contracts\TagRepositoryInterface::class => \App\Repositories\Eloquent\TagRepository::class,
        AttributeRepositoryInterface::class => AttributeRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        TenantProductRepositoryInterface::class => TenantProductRepository::class,
        CityRepositoryInterface::class => CityRepository::class,
        BranchRepositoryInterface::class => BranchRepository::class,
        ReportRepositoryInterface::class => ReportRepository::class,
        \App\Repositories\Contracts\AiConversationRepositoryInterface::class => \App\Repositories\Eloquent\AiConversationRepository::class,
        \App\Repositories\Contracts\AiTrendRepositoryInterface::class => \App\Repositories\Eloquent\AiTrendRepository::class,
        \App\Repositories\Contracts\AiGenerationRepositoryInterface::class => \App\Repositories\Eloquent\AiGenerationRepository::class,
        SupplierRepositoryInterface::class => SupplierRepository::class,
        ShippingCompanyRepositoryInterface::class => ShippingCompanyRepository::class,
        PaymentMethodRepositoryInterface::class => PaymentMethodRepository::class,
        BannerRepositoryInterface::class => BannerRepository::class,
        ExpenseCategoryRepositoryInterface::class => ExpenseCategoryRepository::class,
        ExpenseRepositoryInterface::class => ExpenseRepository::class,
        PurchaseOrderRepositoryInterface::class => PurchaseOrderRepository::class,
        StockTransferRepositoryInterface::class => StockTransferRepository::class,
        StockAdjustmentRepositoryInterface::class => StockAdjustmentRepository::class,
        StockLevelRepositoryInterface::class => StockLevelRepository::class,
        StockMovementRepositoryInterface::class => StockMovementRepository::class,
        DiscountCodeRepositoryInterface::class => DiscountCodeRepository::class,
        FlashSaleRepositoryInterface::class => FlashSaleRepository::class,
        BundleRepositoryInterface::class => BundleRepository::class,
        PackagingOptionRepositoryInterface::class => PackagingOptionRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        OrderReturnRepositoryInterface::class => OrderReturnRepository::class,
        CustomerRepositoryInterface::class => CustomerRepository::class,
        CustomerAddressRepositoryInterface::class => CustomerAddressRepository::class,
        CartRepositoryInterface::class => CartRepository::class,
        \App\Repositories\Contracts\QrCodeRepositoryInterface::class => \App\Repositories\Eloquent\QrCodeRepository::class,
        \App\Repositories\Contracts\RetentionRepositoryInterface::class => \App\Repositories\Eloquent\RetentionRepository::class,
    ];
}
