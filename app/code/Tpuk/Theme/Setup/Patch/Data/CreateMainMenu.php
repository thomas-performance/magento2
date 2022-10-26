<?php

declare(strict_types=1);

namespace Tpuk\Theme\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magezon\NinjaMenus\Model\MenuFactory;
use Magezon\NinjaMenus\Model\ResourceModel\Menu;

/**
* Patch is mechanism, that allows to do atomic upgrade data changes
*/
class CreateMainMenu implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @var MenuFactory
     */
    private $menuFactory;

    /**
     * @var Menu
     */
    private $resource;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param MenuFactory $menuFactory
     * @param Menu $resource
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        MenuFactory $menuFactory,
        Menu $resource
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->menuFactory = $menuFactory;
        $this->resource = $resource;
    }

    /**
     * @return CreateMainMenu
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();
        /** @var \Magezon\NinjaMenus\Model\Menu $menu */
        $menu = $this->menuFactory->create();

        $menu->setIsActive(true)
            ->setIdentifier('top-menu')
            ->setName('Main Menu')
            ->setType('horizontal')
            ->setMobileType('accordion')
            ->setMobileBreakpoint('768')
            ->setHamburgerTitle('Menu')
            ->setCssClasses('')
            ->setMainColor('')
            ->setMainBackgroundColor('')
            ->setSecondaryColor('')
            ->setSecondaryBackgroundColor('')
            ->setMainFontSize('')
            ->setMainFontWeight('')
            ->setMainHoverColor('')
            ->setMainHoverBackgroundColor('')
            ->setSecondaryHoverColor('')
            ->setSecondaryHoverBackgroundColor('')
            ->setOverlayOpacity('')
            ->setHoverDelayTimeout('')
            ->setStoreId('0');

        $this->resource->save($menu);

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
