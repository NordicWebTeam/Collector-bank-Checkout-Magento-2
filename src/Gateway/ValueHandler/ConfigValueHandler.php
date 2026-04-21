<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Gateway\ValueHandler;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Webbhuset\CollectorCheckout\Config\Config;
use Magento\Payment\Gateway\Helper\SubjectReader;

/**
 * Handler for config values
 * Needed because the 'active' config path is not default
 */
class ConfigValueHandler implements ValueHandlerInterface
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function handle(array $subject, $storeId = null)
    {
        $field = SubjectReader::readField($subject);
        $this->config->setStoreId((int)$storeId);
        if ($field === 'active') {
            return $this->config->getIsActive();
        }
        return $this->config->getValue($field, $storeId);
    }
}
