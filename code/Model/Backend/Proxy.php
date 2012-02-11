<?php
/**
 * This backend assumes the use of a reverse proxy and dumps urls to invalidate out to
 * plain text files in var/diehard so that cache clearing can happen externally.
 *
 * @package     Cm_Diehard
 * @author      Colin Mollenhour
 */
class Cm_Diehard_Model_Backend_Proxy extends Cm_Diehard_Model_Backend_Abstract
{

    const CACHE_TAG = 'DIEHARD_URLS';
    const PREFIX_TAG = 'DIEHARD_URLS_';
    const PREFIX_KEY = 'URL_';

    protected $_name = 'Proxy';

    protected $_useAjax = TRUE;
    
    /**
     * Clean all urls.
     */
    public function flush()
    {
        $this->_cleanCache(array(self::CACHE_TAG));
    }

    /**
     * Clean urls associated with given tags
     *
     * @param array $tags
     */
    public function cleanCache($tags)
    {
        // Get all urls related to the tags being cleaned
        $this->_cleanCache($this->_getCacheTags($tags));
    }

    /**
     * Cache a url so that when the associated tags are cleared the url can be added to a list of urls to be
     * invalidated by an external process.
     *
     * Set the Cache-Control header so the proxy will cache the response.
     *
     * @param Mage_Core_Controller_Response_Http $response
     * @param $lifetime
     */
    public function httpResponseSendBefore(Mage_Core_Controller_Response_Http $response, $lifetime)
    {
        // Cache the url with all of the related tags (prefixed)
        $url = $this->helper()->getCurrentUrl();
        $cacheKey = $this->getCacheKey();
        $tags = $this->_getCacheTags($this->helper()->getTags());
        $tags[] = self::CACHE_TAG;
        Mage::app()->saveCache($url, self::PREFIX_KEY.$cacheKey, $tags, $lifetime);

        // Set a header so the page is cached
        $cacheControl = sprintf(Mage::getStoreConfig('system/diehard/cachecontrol'), $lifetime);
        $response->setHeader('Cache-Control', $cacheControl, true);
    }

    /**
     * Add prefix to all tags
     *
     * @param array $tags
     * @return array
     */
    protected function _getCacheTags($tags)
    {
        $saveTags = array();
        foreach($tags as $tag) {
            $saveTags[] = self::PREFIX_TAG.$tag;
        }
        return $saveTags;
    }

    /**
     * Gets the associated urls and dumps them out to a file in the var/diehard directory to be
     * invalidated using an external script.
     *
     * @param $tags
     */
    protected function _cleanCache($tags)
    {
        // Get all urls related to the tags being cleaned
        $ids = Mage::app()->getCache()->getBackend()->getIdsMatchingAnyTags($tags);
        $urls = array();
        foreach($ids as $id) {
            if($url = Mage::app()->loadCache($id)) {
                $urls[] = $url;
            }
        }

        // Clean up the cache
        Mage::app()->getCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);

        // Dump urls to file
        $dir = Mage::getBaseDir('var').DS.'diehard'.DS.'proxy';
        if( ! is_dir($dir)) {
            if( ! mkdir($dir, 770, TRUE)) {
                Mage::log('Could not create diehard directory: '.$dir, Zend_Log::CRIT);
            }
        }
        file_put_contents($dir.DS.sha1(microtime()), implode("\n", $urls));
    }

}
