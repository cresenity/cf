<?php

defined('SYSPATH') or die('No direct access allowed.');

trait CObservable_Listener_Trait_HandlerTrait {
    /**
     * @return \CObservable_Listener_Handler_ReloadHandler
     */
    public function addReloadHandler() {
        $handler = new CObservable_Listener_Handler_ReloadHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_ReloadElementHandler
     */
    public function addReloadElementHandler() {
        $handler = new CObservable_Listener_Handler_ReloadElementHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_ReloadDataTableHandler
     */
    public function addReloadDataTableHandler() {
        $handler = new CObservable_Listener_Handler_ReloadDataTableHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_DispatchWindowEventHandler
     */
    public function addDispatchWindowEventHandler() {
        $handler = new CObservable_Listener_Handler_DispatchWindowEventHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_AppendHandler
     */
    public function addAppendHandler() {
        $handler = new CObservable_Listener_Handler_AppendHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_PrependHandler
     */
    public function addPrependHandler() {
        $handler = new CObservable_Listener_Handler_PrependHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_DialogHandler
     */
    public function addDialogHandler() {
        $handler = new CObservable_Listener_Handler_DialogHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_CloseDialogHandler
     */
    public function addCloseDialogHandler() {
        $handler = new CObservable_Listener_Handler_CloseDialogHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_CloseAllDialogHandler
     */
    public function addCloseAllDialogHandler() {
        $handler = new CObservable_Listener_Handler_CloseAllDialogHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_AjaxSubmitHandler
     */
    public function addAjaxSubmitHandler() {
        $handler = new CObservable_Listener_Handler_AjaxSubmitHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_AjaxHandler
     */
    public function addAjaxHandler() {
        $handler = new CObservable_Listener_Handler_AjaxHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_RemoveHandler
     */
    public function addRemoveHandler() {
        $handler = new CObservable_Listener_Handler_RemoveHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_ToggleActiveHandler
     */
    public function addToggleActiveHandler() {
        $handler = new CObservable_Listener_Handler_ToggleActiveHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_ToastHandler
     */
    public function addToastHandler() {
        $handler = new CObservable_Listener_Handler_ToastHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_RedirectHandler
     */
    public function addRedirectHandler() {
        $handler = new CObservable_Listener_Handler_RedirectHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_SubmitHandler
     */
    public function addSubmitHandler() {
        $handler = new CObservable_Listener_Handler_SubmitHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_CustomHandler
     */
    public function addCustomHandler() {
        $handler = new CObservable_Listener_Handler_CustomHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }

    /**
     * @return \CObservable_Listener_Handler_DownloadProgressHandler
     */
    public function addDownloadProgressHandler() {
        $handler = new CObservable_Listener_Handler_DownloadProgressHandler($this);
        $this->handlers[] = $handler;

        return $handler;
    }
}
