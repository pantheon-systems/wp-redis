<?php
// features/bootstrap/WPRedisFeatureContext.php

namespace behat\features\bootstrap;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;

class WpRedisFeatureContext extends RawMinkContext implements Context
{

    /**
     * Waits a certain number of seconds.
     *
     * @param int $seconds
     *   How long to wait.
     *
     * @When I wait :seconds second(s)
     */
    public function wait($seconds)
    {
        sleep($seconds);
    }

    /**
     * Presses a button located inside a specific element.
     *
     * Needed on wp-admin pages under the browserkit driver. Mink's named
     * "button" selector is a union that also matches <button> elements and
     * anything with role="button", and the driver resolves that union with
     * getNode(0) -- the first match in document order. The admin sidebar's
     * <button id="collapse-button"> (Collapse Menu) appears before the page
     * content, so it wins, and because it has no form ancestor the driver
     * throws "The selected node does not have a form ancestor."
     *
     * The goutte driver did not hit this, which is why these steps passed
     * before the driver swap.
     *
     * Scoping the lookup to the settings form excludes the sidebar button.
     *
     * @When I press :button in the :element element
     */
    public function pressButtonInElement($button, $element)
    {
        $scope = $this->getSession()->getPage()->find('css', $element);

        if (null === $scope) {
            throw new \Exception(sprintf('Element "%s" not found on the page.', $element));
        }

        $scope->pressButton($button);
    }
}
