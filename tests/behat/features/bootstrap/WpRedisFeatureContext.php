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
     * TEMPORARY DEBUG: reproduce the driver's own resolution of a button press.
     *
     * Mink resolves exactly one correct node for this locator and that node has
     * a form ancestor, yet press() throws "does not have a form ancestor". This
     * dumps the driver's view: the XPath it is handed, how many nodes IT
     * resolves from that XPath, and the ancestor chain of getNode(0) -- which
     * is what Crawler::form() actually operates on.
     *
     * @Then debug press :locator
     */
    public function debugPress($locator)
    {
        $page = $this->getSession()->getPage();
        $driver = $this->getSession()->getDriver();

        $button = $page->findButton($locator);
        if (null === $button) {
            echo "=== DEBUG: findButton('$locator') returned NULL ===\n";
            return;
        }

        $xpath = $button->getXpath();
        echo "=== DEBUG PRESS '$locator' ===\n";
        echo "Mink element xpath (this is what gets handed to the driver):\n";
        echo "  " . preg_replace('/\s+/', ' ', $xpath) . "\n";

        // Re-run that exact XPath through the same crawler the driver uses.
        $ref = new \ReflectionClass($driver);
        try {
            $m = $ref->getMethod('getCrawler');
            $m->setAccessible(true);
            $crawler = $m->invoke($driver);

            $filtered = $crawler->filterXPath($xpath);
            echo "driver filterXPath() node count: " . count($filtered) . "\n";

            foreach ($filtered as $i => $n) {
                $chain = [];
                for ($p = $n->parentNode; $p && $p->nodeName !== '#document'; $p = $p->parentNode) {
                    $chain[] = $p->nodeName . ($p instanceof \DOMElement && $p->getAttribute('id') ? '#' . $p->getAttribute('id') : '');
                }
                $hasForm = in_array('form', array_map(function ($s) {
                    return explode('#', $s)[0];
                }, $chain), true);
                printf(
                    "  [%d] <%s> id=%s name=%s value=%s form_ancestor=%s\n       chain: %s\n",
                    $i,
                    $n->nodeName,
                    $n instanceof \DOMElement ? ($n->getAttribute('id') ?: '-') : '-',
                    $n instanceof \DOMElement ? ($n->getAttribute('name') ?: '-') : '-',
                    $n instanceof \DOMElement ? ($n->getAttribute('value') ?: '-') : '-',
                    $hasForm ? 'YES' : 'NO',
                    implode(' > ', array_slice($chain, 0, 7))
                );
            }

            // This is the exact call that throws inside the driver.
            try {
                $form = $filtered->form();
                echo "Crawler::form() OK -> action=" . $form->getUri() . " method=" . $form->getMethod() . "\n";
            } catch (\Throwable $e) {
                echo "Crawler::form() THREW: " . get_class($e) . ": " . $e->getMessage() . "\n";
            }
        } catch (\Throwable $e) {
            echo "reflection failed: " . $e->getMessage() . "\n";
        }

        // Dump every <form> the driver's crawler sees, plus the raw markup
        // around the settings form, so we can see WHY the form tag is absent
        // from the parsed tree on this page but present on profile.php.
        try {
            $m2 = $ref->getMethod('getCrawler');
            $m2->setAccessible(true);
            $cr = $m2->invoke($driver);
            $forms = $cr->filterXPath('//form');
            echo "forms in parsed tree: " . count($forms) . "\n";
            foreach ($forms as $i => $f) {
                echo sprintf(
                    "  form[%d] id=%s action=%s parent=%s\n",
                    $i,
                    $f->getAttribute('id') ?: '-',
                    $f->getAttribute('action') ?: '-',
                    $f->parentNode->nodeName . ($f->parentNode instanceof \DOMElement && $f->parentNode->getAttribute('id') ? '#' . $f->parentNode->getAttribute('id') : '')
                );
            }
            $raw = $this->getSession()->getPage()->getContent();
            $formPos = strpos($raw, 'action="options.php"');
            $btnPos  = strpos($raw, 'id="submit"');
            echo "raw: form tag at $formPos, submit button at $btnPos\n";
            if ($formPos !== false && $btnPos !== false && $btnPos > $formPos) {
                $between = substr($raw, $formPos, $btnPos - $formPos);
                echo "bytes between: " . strlen($between) . "\n";
                echo "</form> count between: " . substr_count($between, '</form>') . "\n";
                echo "<form count between: " . substr_count(strtolower($between), '<form') . "\n";
                // Tag-balance scan: which element closes without being open?
                preg_match_all('/<(\/?)([a-z0-9]+)[^>]*>/i', $between, $m, PREG_OFFSET_CAPTURE);
                $stack = []; $void = ['input','br','img','hr','meta','link','source','track','area','base','col','embed','param','wbr'];
                foreach ($m[0] as $k => $tag) {
                    $close = $m[1][$k][0] === '/';
                    $name  = strtolower($m[2][$k][0]);
                    if (in_array($name, $void, true)) { continue; }
                    if (!$close) { $stack[] = $name; continue; }
                    if (!in_array($name, $stack, true)) {
                        echo "STRAY CLOSE </$name> at offset " . ($formPos + $tag[1]) . "\n";
                        echo "  context: " . preg_replace('/\s+/', ' ', substr($raw, max(0, $formPos + $tag[1] - 220), 300)) . "\n";
                        continue;
                    }
                    while (($pop = array_pop($stack)) !== null && $pop !== $name) {
                        echo "IMPLICITLY CLOSED <$pop> by </$name> at offset " . ($formPos + $tag[1]) . "\n";
                    }
                }
                echo "still-open at button: " . implode(' > ', $stack) . "\n";
            }
        } catch (\Throwable $e) {
            echo "form dump failed: " . $e->getMessage() . "\n";
        }

        // And the real press, to confirm the same failure.
        try {
            $button->press();
            echo "press() OK\n";
        } catch (\Throwable $e) {
            echo "press() THREW: " . get_class($e) . ": " . $e->getMessage() . "\n";
        }
        echo "=== END DEBUG PRESS ===\n";
    }
}
