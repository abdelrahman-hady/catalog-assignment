<?php

namespace Scandiweb\Test\Setup\Patch\Data;

use Exception;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddNewProduct implements DataPatchInterface
{
    /**
     * @var State
     */
    protected State $appState;

    /**
     * @var ProductInterfaceFactory
     */
    protected ProductInterfaceFactory $productFactory;

    /**
     * @var EavSetup
     */
    protected EavSetup $eavSetup;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var SourceItemInterfaceFactory
     */
    protected SourceItemInterfaceFactory $sourceItemFactory;

    /**
     * @var SourceItemsSaveInterface
     */
    protected SourceItemsSaveInterface $sourceItemsSaveInterface;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $categoryCollectionFactory;

    /**
     * @var CategoryLinkManagementInterface
     */
    protected CategoryLinkManagementInterface $categoryLink;

    /**
     * @var array
     */
    protected array $sourceItems;

    /**
     * @param State $appState
     * @param ProductInterfaceFactory $productFactory
     * @param EavSetup $eavSetup
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param CollectionFactory $categoryCollectionFactory
     * @param CategoryLinkManagementInterface $categoryLink
     */
    public function __construct(
        State $appState,
        ProductInterfaceFactory $productFactory,
        EavSetup $eavSetup,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CollectionFactory $categoryCollectionFactory,
        CategoryLinkManagementInterface $categoryLink
    ) {
        $this->appState = $appState;
        $this->productFactory = $productFactory;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryLink = $categoryLink;
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Add new product
     * @return void
     * @throws Exception
     */
    public function apply(): void
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    /**
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws ValidationException
     */
    public function execute(): void
    {
        // Create the product
        $product = $this->productFactory->create();

        // Check if the product already exists
        if ($product->getIdBySku('polo-t-shirt')) {
            return;
        }

        // Set attributes
        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId($attributeSetId)
            ->setName('Polo T-shirt')
            ->setSku('polo-t-shirt')
            ->setPrice(9.99)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(
                [
                    'use_config_manage_stock' => 1,
                    'is_qty_decimal' => 0,
                    'is_in_stock' => 1,
                    'qty' => 99
                ]
            );

        // Save the product to repository
        $product = $this->productRepository->save($product);

        // Create a source item
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setQuantity(10);
        // Add the product's sku that will be linked to this source item
        $sourceItem->setSku($product->getSku());
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItem;
        // Save the source item
        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        // Get category ID
        $categoryIDs = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', ['eq' => 'Default Category'])
            ->getAllIds();
        // Add the product to category
        $this->categoryLink->assignProductToCategories($product->getSku(), $categoryIDs);
    }
}
