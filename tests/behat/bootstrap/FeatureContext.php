<?php

use Behat\Behat\Context\Context;

class FeatureContext implements Context
{
	/**
	 * Waits a certain number of seconds.
	 *
	 * @param int $seconds
	 *   How long to wait.
	 *
	 * @When I wait :seconds second(s)
	 */
	public function wait($seconds) {
		sleep($seconds);
	}

}
