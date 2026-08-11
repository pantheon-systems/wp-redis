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
     * TEMPORARY DEBUG: dump DOM structure around submit buttons.
     *
     * Diagnosing why `I press "submit"` throws "The selected node does not
     * have a form ancestor" under the browserkit driver on Pantheon-served
     * admin pages, when the identical page submits fine under goutte (see the
     * last green run on main) and on a stock local WordPress install.
     *
     * @Then debug form structure
     */
    public function debugFormStructure()
    {
        $html = $this->getSession()->getPage()->getContent();

        echo "=== DEBUG: page bytes=" . strlen($html) . " ===\n";
        echo "form open tags: " . preg_match_all('/<form\b/i', $html) . "\n";
        echo "form close tags: " . preg_match_all('/<\/form>/i', $html) . "\n";

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        echo "libxml errors: " . count($errors) . "\n";
        foreach (array_slice($errors, 0, 15) as $e) {
            echo sprintf("  line %d: %s", $e->line, trim($e->message)) . "\n";
        }

        // Ask Mink itself which node it resolves for the "submit" locator, rather
        // than guessing with a hand-written xpath.
        $page = $this->getSession()->getPage();
        $matched = $page->findAll('named', ['button', 'submit']);
        echo "Mink matched " . count($matched) . " node(s) for locator 'submit':\n";
        foreach ($matched as $j => $el) {
            echo sprintf(
                "  <%s> id=%s name=%s value=%s xpath=%s\n",
                $el->getTagName(),
                $el->getAttribute('id') ?: '-',
                $el->getAttribute('name') ?: '-',
                $el->getAttribute('value') ?: '-',
                $el->getXpath()
            );
        }

        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//input[@type="submit"] | //button[@type="submit"] | //*[@role="button"] | //button') as $i => $node) {
            $chain = [];
            for ($p = $node->parentNode; $p && $p->nodeName !== '#document'; $p = $p->parentNode) {
                $chain[] = $p->nodeName . ($p->getAttribute('id') ? '#' . $p->getAttribute('id') : '');
            }
            printf(
                "[%d] id=%s name=%s value=%s form_ancestor=%s\n     chain: %s\n",
                $i,
                $node->getAttribute('id') ?: '-',
                $node->getAttribute('name') ?: '-',
                $node->getAttribute('value') ?: '-',
                in_array('form', array_map(function ($s) {
                    return explode('#', $s)[0];
                }, $chain), true) ? 'YES' : 'NO',
                implode(' > ', array_slice($chain, 0, 8))
            );
        }

        // Show raw markup immediately before the settings form, where a stray
        // close tag from an admin notice would sit.
        $pos = strpos($html, 'action="options.php"');
        if ($pos !== false) {
            echo "--- 600 bytes before settings form ---\n";
            echo substr($html, max(0, $pos - 600), 600) . "\n";
        }
        echo "=== END DEBUG ===\n";
    }
}
