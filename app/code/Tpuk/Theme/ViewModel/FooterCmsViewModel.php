<?php

declare(strict_types=1);

namespace Tpuk\Theme\ViewModel;

use Magento\Cms\Model\BlockRepository;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\Context;
use Magento\Store\Model\StoreManagerInterface;

class FooterCmsViewModel implements ArgumentInterface
{
    /**
     * @var BlockRepository
     */
    private $blockRepository;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param BlockRepository $blockRepository
     * @param FilterProvider $filterProvider
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        BlockRepository $blockRepository,
        FilterProvider $filterProvider,
        StoreManagerInterface $storeManager
    ) {
        $this->blockRepository = $blockRepository;
        $this->filterProvider = $filterProvider;
        $this->storeManager = $storeManager;
    }

    /**
     * @param string $id
     * @return string
     * @throws NoSuchEntityException
     */
    public function getTitle(string $id): string
    {
        $block = $this->blockRepository->getById($id);

        $filter = $this->filterProvider->getBlockFilter()->setStoreId(
            $this->storeManager->getStore()->getId()
        );

        return $filter->filter($block->getTitle());
    }

    /**
     * @param string $id
     * @return string
     * @throws NoSuchEntityException
     */
    public function getContent(string $id): string
    {
        $block = $this->blockRepository->getById($id);

        $filter = $this->filterProvider->getBlockFilter()->setStoreId(
            $this->storeManager->getStore()->getId()
        );

        return $filter->filter($block->getContent());
    }
}
