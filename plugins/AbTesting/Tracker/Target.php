<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\AbTesting\Tracker;

use Piwik\Piwik;
use Piwik\Tracker\Request;
use Piwik\UrlHelper;

class Target
{
    const ATTRIBUTE_URL = 'url';
    const ATTRIBUTE_PATH = 'path';
    const ATTRIBUTE_URLPARAM = 'urlparam';

    const TYPE_ANY = 'any';
    const TYPE_EXISTS = 'exists';
    const TYPE_EQUALS_SIMPLE = 'equals_simple';
    const TYPE_EQUALS_EXACTLY = 'equals_exactly';
    const TYPE_CONTAINS = 'contains';
    const TYPE_STARTS_WITH = 'starts_with';
    const TYPE_REGEXP = 'regexp';

    /**
     * @var array
     */
    private $target;

    public function __construct($target)
    {
        $this->target = $target;
    }

    /**
     * Check if the experiment matches the given target.
     * 
     * @param Request $request
     * @return bool
     * @throws \Exception
     */
    public function matches(Request $request)
    {
        if (empty($this->target['type']) || empty($this->target['attribute'])) {
            return true;
        }

        $url = $request->getParam('url');
        $attributeValue = $this->getValueForAttribute($url);

        switch (strtolower($this->target['attribute'])) {
            case self::ATTRIBUTE_URL:
            case self::ATTRIBUTE_PATH:
                return $this->matchesTargetValue($attributeValue, $this->target['type'], $this->target['inverted'], $this->target['value']);
            case self::ATTRIBUTE_URLPARAM:
                $value2 = null;
                if (isset($this->target['value2'])) {
                    $value2 = $this->target['value2'];
                }
                return $this->matchesTargetValue($attributeValue, $this->target['type'], $this->target['inverted'], $value2);
        }

        return false;
    }

    protected function getValueForAttribute($url)
    {
        switch (strtolower($this->target['attribute'])) {
            case self::ATTRIBUTE_URL:
                return $url;
            case self::ATTRIBUTE_PATH:
                $urlParsed = parse_url($url);
                if (isset($urlParsed['path'])) {
                    return $urlParsed['path'];
                }
                return '';
            case self::ATTRIBUTE_URLPARAM:
                $urlParsed = parse_url($url);
                $targetValue = null;
                if (!empty($urlParsed['query']) && !empty($this->target['value'])) {
                    $paramName = $this->target['value'];
                    $params = UrlHelper::getArrayFromQueryString($urlParsed['query']);
                    if (isset($params[$paramName])) {
                        $targetValue = $params[$paramName];
                    }
                }
                return $targetValue;
        }
    }

    private function removeWwwSubdomain($host)
    {
        return str_replace('www.', '', $host);
    }

    protected function matchesTargetValue($attributeValue, $type, $invert, $valueToMatch)
    {
        $matches = false;

        if (is_string($attributeValue)) {
            $attributeValue = strtolower($attributeValue);
        }

        if (is_string($valueToMatch) && $type !== 'regexp') {
            $valueToMatch = strtolower($valueToMatch);
        }

        switch ($type) {
            case self::TYPE_ANY:
                $matches = true;
                break;
            case self::TYPE_EXISTS:
                if ($attributeValue !== null) {
                    $matches = true;
                }
                break;
            case self::TYPE_EQUALS_SIMPLE:
                $parsedActual = parse_url($attributeValue);
                $parsedMatch = parse_url($valueToMatch);

                if (isset($parsedActual['host'])) {
                    $parsedActual['host'] = $this->removeWwwSubdomain($parsedActual['host']);
                }
                if (isset($parsedMatch['host'])) {
                    $parsedMatch['host'] = $this->removeWwwSubdomain($parsedMatch['host']);
                }

                if (!isset($parsedMatch['host']) || $parsedActual['host'] == $parsedMatch['host']) {
                    if (!isset($parsedActual['path']) && !isset($parsedMatch['path'])) {
                        $matches = true;
                    } elseif (isset($parsedActual['path']) && isset($parsedMatch['path'])) {
                        if ($parsedActual['path'] == $parsedMatch['path'] ||
                            $parsedActual['path'] == $parsedMatch['path'] . '/' ||
                            $parsedActual['path'] == '/' . $parsedMatch['path'] ||
                            $parsedActual['path'] == '/' . $parsedMatch['path'] . '/' ||
                            $parsedActual['path'] . '/' == $parsedMatch['path']) {
                            $matches = true;
                        }
                    }
                }

                break;
            case self::TYPE_EQUALS_EXACTLY:
                if ($attributeValue && $attributeValue === $valueToMatch) {
                    $matches = true;
                }

                if (@parse_url($valueToMatch, PHP_URL_PATH) === '/' && $valueToMatch === $attributeValue . '/') {
                    $matches = true;
                }

                if (@parse_url($attributeValue, PHP_URL_PATH) === '/' && $attributeValue === $valueToMatch . '/') {
                    $matches = true;
                }

                break;
            case self::TYPE_CONTAINS:
                if ($attributeValue && strpos($attributeValue, $valueToMatch) !== false) {
                    $matches = true;
                }
                break;
            case self::TYPE_STARTS_WITH:
                if ($attributeValue && strpos($attributeValue, $valueToMatch) === 0) {
                    $matches = true;
                }
                break;
            case self::TYPE_REGEXP:
                $pattern = '/' . str_replace('/', '\/', $valueToMatch) . '/';
                if (preg_match($pattern, $attributeValue)) {
                    $matches = true;
                }
                break;
        }

        if ($invert) {
            return !$matches;
        }

        return $matches;
    }

    public static function doesTargetTypeRequireValue($type)
    {
        return $type !== self::TYPE_ANY;
    }
    
    public static function getAvailableTargetTypes()
    {
        $targetTypes = array();

        $urlOptions = array(
            Target::TYPE_EQUALS_EXACTLY => Piwik::translate('AbTesting_TargetTypeEqualsExactly'),
            Target::TYPE_EQUALS_SIMPLE => Piwik::translate('AbTesting_TargetTypeEqualsSimple'),
            Target::TYPE_CONTAINS => Piwik::translate('AbTesting_TargetTypeContains'),
            Target::TYPE_STARTS_WITH => Piwik::translate('AbTesting_TargetTypeStartsWith'),
            Target::TYPE_REGEXP => Piwik::translate('AbTesting_TargetTypeRegExp'),
        );

        $urlAttribute = array(
            'value' => Target::ATTRIBUTE_URL,
            'name' => Piwik::translate('AbTesting_TargetAttributeUrl'),
            'types' => array(),
            'example' => 'http://www.example.com/' . Piwik::translate('AbTesting_FilesystemDirectory')
        );
        foreach ($urlOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }
        $targetTypes[] = $urlAttribute;


        $urlAttribute = array(
            'value' => Target::ATTRIBUTE_PATH,
            'name' => Piwik::translate('AbTesting_TargetAttributePath'),
            'types' => array(),
            'example' => '/' . Piwik::translate('AbTesting_FilesystemDirectory')
        );
        foreach ($urlOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }
        $targetTypes[] = $urlAttribute;


        $urlAttribute = array(
            'value' => Target::ATTRIBUTE_URLPARAM,
            'name' => Piwik::translate('AbTesting_TargetAttributeUrlParameter'),
            'types' => array(),
            'example' => Piwik::translate('AbTesting_TargetAttributeUrlParameterExample')
        );
        
        $parameterOptions = array(
            Target::TYPE_EXISTS => Piwik::translate('AbTesting_TargetTypeExists'),
            Target::TYPE_EQUALS_EXACTLY => Piwik::translate('AbTesting_TargetTypeEqualsExactly'),
            Target::TYPE_CONTAINS => Piwik::translate('AbTesting_TargetTypeContains'),
            Target::TYPE_REGEXP => Piwik::translate('AbTesting_TargetTypeRegExp'),
        );

        foreach ($parameterOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }

        $targetTypes[] = $urlAttribute;

        return $targetTypes;
    }

}
