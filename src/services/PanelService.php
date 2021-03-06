<?php
/* =====================================================================
 * atk-wordpress => Wordpress interface for Agile Toolkit Framework.
 *
 * This interface enable the use of the Agile Toolkit framework within a WordPress site.
 *
 * Please note that when atk is mentioned it generally refer to Agile Toolkit.
 * More information on Agile Toolkit: http://www.agiletoolkit.org
 *
 * Author: Alain Belair
 * https://github.com/ibelar
 * Licensed under MIT
 * =====================================================================*/
/**
 * Responsible for creating and registering all WP action
 * needed for panel.
 */

namespace atkwp\services;

use atkwp\interfaces\ComponentCtrlInterface;

/**
 * This service is responsible for loading and registering
 * all panels set in config file.
 *
 * Panel can be defined as:
 *      panel for Wp admin section page (config-panel)
 *
 * Panel are atk views that are echo within Wordpress admin section.
 * Every panels defined will be registered in $ctrl->component array.
 */
class PanelService
{
    /**
     * The component controller responsible of initiating this service.
     *
     * @var ComponentCtrlInterface
     */
    private $ctrl;

    /**
     * The executable need to output panel component within Wp.
     *
     * @var callable
     */
    protected $executable;

    /**
     * The panels registered within this services.
     *
     * @var array
     */
    protected $panels = [];

    /**
     * The url to assets directory where we can find panel icon.
     *
     * @var string
     */
    protected $iconUrl;

    /**
     * PanelService constructor.
     *
     * @param ComponentCtrlInterface $ctrl
     * @param array                  $panels
     * @param callable               $callable
     * @param string                 $url
     */
    public function __construct(ComponentCtrlInterface $ctrl, array $panels, $callable, $url)
    {
        $this->ctrl = $ctrl;
        $this->executable = $callable;
        $this->iconUrl = $url;
        $this->setPanels($panels);
        $this->registerPanels();

        //register panel components with ctrl ounce fully loaded and with hook setting in place.
        add_action('admin_init', function () {
            $this->ctrl->registerComponents('panel', $this->getPanels());
        });
    }

    /**
     * Return panels array.
     *
     * @return array
     */
    public function getPanels()
    {
        return $this->panels;
    }

    public function setPanels($panels)
    {
        //add id key to our panels
        foreach ($panels as $key => $panel) {
            $panels[$key]['id'] = $key;
        }
        $this->panels = $panels;
    }

    /**
     * Setup panel for WP.
     */
    protected function registerPanels()
    {
        //start by loading our main panel
        if ($panels = $this->getPanelsByType('panel')) {
            foreach ($panels as $key => $panel) {
                $this->registerPanel($key, $panel);
            }
        }
    }

    /**
     * This will register the panel within Wordpress by setting the proper action hook.
     * Will take the uses attribute to load (add) the proper panel class.
     * Will also add sub panel define for a panel.
     *
     * @param $key
     * @param $panel
     */
    protected function registerPanel($key, $panel)
    {
        //check if panel has sub panel
        $subPanels = $this->getSubPanels($key);
        add_action('admin_menu', function () use ($key, $panel, $subPanels) {
            $this->createPanelMenu($key, $panel);
            if (isset($subPanels) && !empty($subPanels)) {
                foreach ($subPanels as $key => $subPanel) {
                    $this->createSubPanelMenu($key, $subPanel);
                }
            }
        });
    }

    /**
     * This is the actual implementation of the panel.
     * Registering the add_menu_page action in Wordpress that will render the html for the panel.
     * When this panel menu is selected, It will run the function $this->executable.
     *
     * @param $key
     * @param $panel
     */
    private function createPanelMenu($key, $panel)
    {
        $iconUrl = null;
        if (isset($panel['icon']) && !empty($panel['icon'])) {
            if (strpos($panel['icon'], 'dashicons') === false) {
                $iconUrl = "{$this->iconUrl}/{$panel['icon']}";
            } else {
                $iconUrl = $panel['icon'];
            }
        }
        $hook = add_menu_page(
            $panel['page'],
            $panel['menu'],
            $panel['capabilities'],
            $panel['slug'],
            $this->executable,
            $iconUrl,
            $panel['position']
        );
        $this->registerPanelHook($key, $hook);
    }

    private function createSubPanelMenu($key, $panel)
    {
        $hook = add_submenu_page(
            $this->getPanelParent($panel),
            $panel['page'],
            $panel['menu'],
            $panel['capabilities'],
            $panel['slug'],
            $this->executable
        );
        $this->registerPanelHook($key, $hook);
    }

    private function getPanelParent($panel)
    {
        if ($panel['type'] === 'sub-panel') {
            $parentSlug = $this->getPanelSlugByKey($panel['parent']);
        }
        if ($panel['type'] === 'wp-sub-panel') {
            $parentSlug = $panel['parent'];
        }

        return $parentSlug;
    }

    private function registerPanelHook($key, $hook)
    {
        $this->panels[$key]['hook'] = $hook;
    }

    private function getPanelsByType($type)
    {
        return array_filter($this->panels, function ($panel) use ($type) {
            if ($panel['type'] === $type) {
                return $panel;
            }
        });
    }

    /**
     * Get sub panel related to a panel.
     *
     * @param $panelKey
     *
     * @return array
     */
    private function getSubPanels($panelKey)
    {
        $relatedPanels = [];
        $subPanels = $this->getPanelsByType('sub-panel');
        if ($subPanels) {
            foreach ($subPanels as $key => $subPanel) {
                if ($subPanel['parent'] === $panelKey) {
                    $relatedPanels[$key] = $subPanel;
                }
            }
        }

        return $relatedPanels;
    }

    private function getPanelSlugByKey($key)
    {
        return $this->panels[$key]['slug'];
    }
}
