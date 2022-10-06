<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Tpuk\Theme\Setup\Patch\Data;

use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
* Patch is mechanism, that allows to do atomic upgrade data changes
*/
class SetHomepageContent implements DataPatchInterface
{
    const PAGE_IDENTIFIER = 'home';

    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @var PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @var PageInterfaceFactory
     */
    private $pageFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param PageRepositoryInterface $pageRepository
     * @param PageInterfaceFactory $pageFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        PageRepositoryInterface $pageRepository,
        PageInterfaceFactory $pageFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->pageRepository = $pageRepository;
        $this->pageFactory = $pageFactory;
    }

    /**
     * @return SetHomepageContent|void
     * @throws LocalizedException
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        try {
            $page = $this->pageRepository->getById(self::PAGE_IDENTIFIER);
        } catch (LocalizedException $e) {
            $page = $this->pageFactory->create();
            $page->setTitle('Home Page')
                ->setIsActive(true)
                ->setIdentifier(self::PAGE_IDENTIFIER);
        }

        $content = file_get_contents(__DIR__ . '/SetHomepageContent/home.html');
        $page->setContent($content)
            ->setContentHeading('')
            ->setPageLayout('1column');

        $this->pageRepository->save($page);
        $this->moduleDataSetup->endSetup();
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }
}
