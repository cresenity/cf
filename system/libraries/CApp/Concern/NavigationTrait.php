<?php

/**
 * Description of Navigation.
 */
trait CApp_Concern_NavigationTrait {
    /**
     * @var CNavigation_Nav
     */
    protected $nav = null;

    /**
     * @var string
     */
    protected $navRenderer = CNavigation_Renderer_SidenavRenderer::class;

    /**
     * @param null|array|Closure|CNavigation_Nav|string $nav
     *
     * @return $this
     */
    public function setNav($nav) {
        $this->nav = $this->resolveNav($nav);

        return $this;
    }

    /**
     * @param null|array|Closure|CNavigation_Nav|string $nav
     *
     * @return CNavigation_Nav
     */
    public function resolveNav($nav) {
        return CNavigation::manager()->resolveNav($nav);
    }

    /**
     * @return null|string
     */
    public function getNavName() {
        return $this->nav ? $this->nav->getName() : null;
    }

    /**
     * Resolve Nav Engine.
     *
     * @param mixed $renderer
     *
     * @return CApp_Navigation_EngineInterface
     */
    public function resolveNavRenderer($renderer = null) {
        return CNavigation::manager()->resolveRenderer($renderer);
    }

    /**
     * Undocumented function.
     *
     * @return null|CNavigation_Nav
     */
    public function getNav() {
        return $this->resolveNav($this->nav);
    }

    /**
     * @return string
     */
    public function getNavRenderer() {
        return $this->navRenderer;
    }

    /**
     * @param string $renderer
     *
     * @return $this
     */
    public function setNavRenderer($renderer) {
        $this->navRenderer = $renderer;

        return $this;
    }
}
