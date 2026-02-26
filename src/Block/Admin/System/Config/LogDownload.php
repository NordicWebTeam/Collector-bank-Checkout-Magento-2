<?php
namespace Webbhuset\CollectorCheckout\Block\Admin\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Block for rendering a Download Logs button in the config section
 */
class LogDownload extends Field
{
    const DOWNLOAD_URI = 'collectoradmin/log/download';

    protected $_template = 'Webbhuset_CollectorCheckout::system/config/log_download.phtml';

    /**
     * Render template instead of element
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate button HTML.
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        );
        /** @var \Magento\Backend\Block\Widget\Button $button */
        $button->setData(
            [
                'id' => 'download_logs_button',
                'label' => __('Download log file'),
                'onclick' => 'setLocation(\'' . $this->getUrl(self::DOWNLOAD_URI) . '\')',
            ]
        );
        return $button->toHtml();
    }
}
