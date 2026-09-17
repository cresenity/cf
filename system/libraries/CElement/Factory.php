<?php

/**
 * Factory for instantiating CElement elements, components, lists, form inputs, views and templates.
 */
class CElement_Factory {
    /**
     * @param string $tag
     * @param string $id  optional
     *
     * @throws CApp_Exception
     *
     * @return \CElement_Element|bool
     */
    public static function createElement($tag = 'div', $id = '') {
        $class_name = 'CElement_Element_';
        switch (strtolower($tag)) {
            case 'div':
            case 'span':
            case 'a':
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
            case 'p':
            case 'ol':
            case 'ul':
            case 'li':
            case 'table':
            case 'th':
            case 'tr':
            case 'td':
            case 'code':
            case 'pre':
            case 'tbody':
            case 'thead':
            case 'iframe':
            case 'blockquote':
            case 'label':
            case 'canvas':
            case 'img':
            case 'button':
                $class_name = 'CElement_Element_' . ucfirst($tag);

                return new $class_name($id);
            default:
                throw new CApp_Exception(c::__('element [:tag] not found', ['tag' => $tag]));
        }
        /** @phpstan-ignore-next-line */
        return false;
    }

    /**
     * @param string $id optional
     *
     * @return \CElement_Template|bool
     */
    public static function createTemplate($id = '') {
        return new CElement_Template($id);
    }

    /**
     * @param string                 $id   optional
     * @param null|CView_View|string $view optional
     * @param null|array             $data optional
     *
     * @return \CElement_View
     */
    public static function createView($id = '', $view = null, $data = null) {
        return new CElement_View($id, $view, $data);
    }

    /**
     * @param string $class
     * @param string $id
     *
     * @return \CElement_FormInput
     */
    public static function createFormInput($class, $id = '') {
        return new $class($id);
    }

    /**
     * @param string $className
     * @param string $id        optional
     *
     * @throws CElement_Exception
     *
     * @return \CElement
     */
    public static function create($className, $id = '') {
        if (!class_exists($className)) {
            throw new CElement_Exception(c::__('Element [:name] not found', ['name' => $className]));
        }

        return new $className($id);
    }

    /**
     * @param string $name
     * @param string $id   optional
     *
     * @throws CElement_Exception
     *
     * @return \CElement_Component
     */
    public static function createComponent($name, $id = '') {
        $className = $name;
        if (!class_exists($className)) {
            $className = 'CElement_Component_' . $name;
        }
        if (!class_exists($className)) {
            throw new CElement_Exception(c::__('component [:name] not found', ['name' => $name]));
        }

        return new $className($id);
    }

    /**
     * @param string $name
     * @param string $id   optional
     *
     * @throws CElement_Exception
     *
     * @return \CElement_List
     */
    public static function createList($name, $id = '') {
        $name = c::classBasename($name);

        $className = 'CElement_List_' . $name;
        if (!class_exists($className)) {
            throw new CElement_Exception(c::__('list element [:name] not found', ['name' => $name]));
        }

        return new $className($id);
    }

    /**
     * @param string $id
     * @param string $type
     *
     * @throws Exception
     *
     * @return CElement_FormInput
     */
    public static function createControl($id, $type) {
        return CManager::instance()->createControl($id, $type);
    }

    /**
     * @param string|null $id
     *
     * @return \CElement_PseudoElement
     */
    public static function createPseudoElement($id = null) {
        return new CElement_PseudoElement($id);
    }
}
