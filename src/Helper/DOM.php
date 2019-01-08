<?php

namespace App\Helper;

class DOM {
    protected $url = null;
    protected $document = null;
    protected $parsedNodes = [];

    public function __construct($url) {
        $this->url = $url;

        if ($html = file_get_contents($url)) {
            $this->document = new \DOMDocument();
            libxml_use_internal_errors(true);
            $this->document->loadHTML($html);
            libxml_use_internal_errors(false);
        } else {
            throw new \Exception('The provided URL is either invalid or does not allow robots to fetch its content.');
        }
    }

    public static function fromUrl($url) {
        return (new static($url));
    }

    public function parseNodes($node = null, $parentSelector = '') {
        if (!$node) {
            if (count($this->parsedNodes)) {
                return null;
            }

            $node = $this->document;
        }

        $content = null;
        if ($node) {
            $content = $node->nodeValue;
            $selector = ($parentSelector ? $parentSelector . ' > ' : '') . $this->getSelectorFromNode($node);

            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $subnode) {
                    if (!in_array($subnode->nodeName, ['#text', '#cdata-section'])) {
                        $content .= $this->parseNodes($subnode, $selector);
                    }
                }
            }

            if (trim($content)) {
                $this->parsedNodes[] = ['node' => $node, 'content' => $content, 'attributes' => $this->getAttributesFromNode($node), 'selector' => $selector];
            }
        }

        return $content;
    }

    public function findElementFromContent($text, $selectorOnly = false) {
        $this->parseNodes();

        $matchingNode = null;
        $matchingPercentage = 0;

        foreach ($this->parsedNodes as $nodeData) {
            $similarityPercentage = 0;
            similar_text($nodeData['content'], $text, $similarityPercentage);

            if ($similarityPercentage > $matchingPercentage) {
                $matchingPercentage = $similarityPercentage;
                $matchingNode = $nodeData;
            }
        }

        if ($selectorOnly) {
            return $this->simplifyUniqueSelector($matchingNode['selector']);
        } else {
            return $matchingNode;
        }
    }

    public function getSelectorFromNode($node, $unique = false) {
        $selector = property_exists($node, 'tagName') ? $node->tagName : '';
        $attributes = $this->getAttributesFromNode($node);

        if ($classes =  array_filter(explode(' ', $attributes['class'] ?? ''))) {
            foreach ($classes as $class) {
                if (1 !== preg_match('~[0-9]~', $class)) {
                    $selector .= '.' . $class;
                }
            }
        }

        if ($id = $attributes['id'] ?? null) {
            if (1 !== preg_match('~[0-9]~', $id)) {
                $selector .= '#' . $id;
            }
        }

        if ($unique) {
            while (!$this->isSelectorIsUnique($selector)) {
                # TODO
            }
        }

        return $selector;
    }

    public function isSelectorIsUnique($selector) {
        $selectors = array_reverse(explode(' ', $selector));
    }

    public function getAttributesFromNode($node) {
        $attributes = [];

        if ($node->attributes) {
            for ($i = 0; $i < $node->attributes->length; $i++) {
                $attribute = $node->attributes->item($i);
                $attributes[$attribute->name] = $attribute->value;
            }
        }

        return $attributes;
    }

    public function simplifyUniqueSelector($selector) {
        $selectorParts = explode(' > ', $selector);
        $simplestUniqueSelector = '';

        while (count($selectorParts) && $simplestUniqueSelector = array_pop($selectorParts) . ($simplestUniqueSelector ? ' > ' . $simplestUniqueSelector : '')) {
            $matchesCount = 0;

            foreach ($this->parsedNodes as $nodeData) {
                if (strlen($nodeData['selector']) >= strlen($simplestUniqueSelector) && substr($nodeData['selector'], strlen($simplestUniqueSelector) * -1) == $simplestUniqueSelector) {
                    $matchesCount++;
                    if ($matchesCount > 1) {
                        break;
                    }
                }
            }

            if ($matchesCount == 1) {
                break;
            }

        }

        return $simplestUniqueSelector;
    }
}
