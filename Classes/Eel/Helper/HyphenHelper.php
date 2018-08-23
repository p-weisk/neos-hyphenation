<?php

namespace PunktDe\Neos\Hyphenation\Eel\Helper;

/*
 * This file is part of the PunktDe.Neos.Hyphenation package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use \Org\Heigl\Hyphenator as h;
use Neos\Flow\Annotations as Flow;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Configuration\Exception\InvalidConfigurationException;
use Neos\Flow\Exception;
use Neos\Flow\Package\PackageManagerInterface;
use Neos\Cache\Frontend\StringFrontend;


class HyphenHelper implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var StringFrontend
     */
    protected $hyphenationCache;

    /**
     * @Flow\Inject
     * @var PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @Flow\InjectConfiguration(path="minimumPadding", package="PunktDe.Neos.Hyphenation")
     * @var array
     */
    protected $padding;

    protected $hyphenator;

    /**
     * @param string $text : the text which will be hyphenated
     * @return string : the hyphenated text (with oft hyphens)
     */
    public function hyphenateText(string $text): string
    {	$this->hyphenator = h\Hyphenator::factory();
    	$this->hyphenator->getOptions()->setLeftMin(4);
    	$this->hyphenator->getOptions()->setRightMin(4);
	$this->hyphenator->getOptions()->setDefaultLocale('de_DE');
	
	$hyphenateLongWords = function ($carry, $item): string {
            $carry .= ' ' . (strlen($item) < 8 ? $item : $this->storeAndRetrieveWord($item, $this->hyphenator));
            return $carry;
        };

        return trim(array_reduce(explode(" ", $text), $hyphenateLongWords));
    }

    /**
     * @param string $word : the raw word to hyphenate and store or to retrieve if aready stored
     * @return string : the hyphenated word
     */
    protected function storeAndRetrieveWord(string $word, $hyphenator): string
    {
        $wordHash = md5($this->padding . $word);
        $cacheEntry = $this->hyphenationCache->get($wordHash);

        if (!$cacheEntry) {
            $cacheEntry = $this->hyphenateWord($word, $hyphenator);
            $this->hyphenationCache->set($wordHash, $cacheEntry);
        }

        return $cacheEntry;
    }

    /**
     * @param string $word : the word to hyphenate
     * @return string : the hyphenated word
     */
    protected function hyphenateWord(string $word, $hyphenator): string
    {
        //$word = str_replace("'", "'\''", $word);
        //$statement = $this->getHyphenationModuleBinPath() . " '" . $word . "' '" . $this->padding . "'";
	//return trim(shell_exec($statement), "\n");
	return $hyphenator->hyphenate($word);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws Exception
     * @return string
     */
    protected function getHyphenationModuleBinPath(): string
    {
        $packageResourcePath = $this->packageManager->getPackage('PunktDe.Neos.Hyphenation')->getResourcesPath();

        $binPath = $packageResourcePath . 'Private/Library/index.js';

        if (!is_file($binPath)) {
            throw new InvalidConfigurationException(sprintf('The hyphenation binary in the configured path "%s" was not found', $binPath), 1534865270);
        }

        if (!is_executable($binPath)) {
            throw new Exception(sprintf('The hyphenation binary in the configured path "%s" is not executable', $binPath), 1534865271);
        }

        return $binPath;
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
