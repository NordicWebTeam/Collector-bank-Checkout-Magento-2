<?php
declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Oath;

use Magento\Framework\App\CacheInterface;
use Webbhuset\CollectorCheckout\Config\StoreConfigFactory;
use Webbhuset\CollectorCheckout\Config\ConfigFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Adapter\GetAccessKeyFactory;

class AccessKeyManager
{
    const CACHE_TTL = 3600; // 1 hour
    const CACHE_NAME = 'WALLEY_OATH_ACCESS_KEY';
    const CACHE_TAGS = 'WALLEY';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var ConfigFactory
     */
    private ConfigFactory $configFactory;

    /**
     * @var GetAccessKeyFactory
     */
    private GetAccessKeyFactory $getAccessKeyFactory;

    public function __construct(
        CacheInterface $cache,
        ConfigFactory $configFactory,
        GetAccessKeyFactory $getAccessKeyFactory
    ) {
        $this->cache = $cache;
        $this->configFactory = $configFactory;
        $this->getAccessKeyFactory = $getAccessKeyFactory;
    }

    public function getAccessKeyByStore(int $storeId)
    {
        $cacheKey = $this->getCacheKey($storeId);
        $accessKey = $this->cache->load($cacheKey);
        if ($accessKey) {
            return $accessKey;
        }
        $accessKey = $this->generateNewAccessKey($storeId);
        $this->cache->save($accessKey,$cacheKey,[self::CACHE_TAGS],self::CACHE_TTL);

        return $accessKey;
    }

    private function getCacheKey(int $storeId):string
    {
        return self::CACHE_NAME . '_' . $storeId;
    }

    public function generateNewAccessKey(int $storeId)
    {
        $config = $this->configFactory->create(['storeId' => $storeId]);
        $makeAccessKeyRequest = $this->getAccessKeyFactory->create([
            'config' => $config
        ]);

        return $makeAccessKeyRequest->getAccessKey();
    }
}
