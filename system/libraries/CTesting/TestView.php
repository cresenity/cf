<?php

class CTesting_TestView {
    use CTrait_Macroable;

    /**
     * The original view.
     *
     * @var \CView_View
     */
    protected $view;

    /**
     * The rendered view contents.
     *
     * @var string
     */
    protected $rendered;

    /**
     * Create a new test view instance.
     *
     * @param \CView_View $view
     *
     * @return void
     */
    public function __construct(CView_View $view) {
        $this->view = $view;
        $this->rendered = $view->render();
    }

    /**
     * Assert that the given string is contained within the view.
     *
     * @param string $value
     * @param bool   $escape
     *
     * @return $this
     */
    public function assertSee($value, $escape = true) {
        $value = $escape ? c::e($value) : $value;

        CTesting_Assert::assertStringContainsString((string) $value, $this->rendered);

        return $this;
    }

    /**
     * Assert that the given strings are contained in order within the view.
     *
     * @param array $values
     * @param bool  $escape
     *
     * @return $this
     */
    public function assertSeeInOrder(array $values, $escape = true) {
        $values = $escape ? array_map('e', ($values)) : $values;

        CTesting_Assert::assertThat($values, new CTesting_Constraint_SeeInOrder($this->rendered));

        return $this;
    }

    /**
     * Assert that the given string is contained within the view text.
     *
     * @param string $value
     * @param bool   $escape
     *
     * @return $this
     */
    public function assertSeeText($value, $escape = true) {
        $value = $escape ? c::e($value) : $value;

        CTesting_Assert::assertStringContainsString((string) $value, strip_tags($this->rendered));

        return $this;
    }

    /**
     * Assert that the given strings are contained in order within the view text.
     *
     * @param array $values
     * @param bool  $escape
     *
     * @return $this
     */
    public function assertSeeTextInOrder(array $values, $escape = true) {
        $values = $escape ? array_map(['c', 'e'], ($values)) : $values;

        CTesting_Assert::assertThat($values, new CTesting_Constraint_SeeInOrder(strip_tags($this->rendered)));

        return $this;
    }

    /**
     * Assert that the given string is not contained within the view.
     *
     * @param string $value
     * @param bool   $escape
     *
     * @return $this
     */
    public function assertDontSee($value, $escape = true) {
        $value = $escape ? c::e($value) : $value;

        CTesting_Assert::assertStringNotContainsString((string) $value, $this->rendered);

        return $this;
    }

    /**
     * Assert that the given string is not contained within the view text.
     *
     * @param string $value
     * @param bool   $escape
     *
     * @return $this
     */
    public function assertDontSeeText($value, $escape = true) {
        $value = $escape ? c::e($value) : $value;

        CTesting_Assert::assertStringNotContainsString((string) $value, strip_tags($this->rendered));

        return $this;
    }

    /**
     * Get the string contents of the rendered view.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        return $this->rendered;
    }
}
