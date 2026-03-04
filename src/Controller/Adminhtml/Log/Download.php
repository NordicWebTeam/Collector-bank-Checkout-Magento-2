<?php declare(strict_types=1);

namespace Webbhuset\CollectorCheckout\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Response\Http\FileFactory;

/**
 * Download log file controller
 */
class Download extends Action
{
    const ADMIN_RESOURCE = 'Webbhuset_CollectorCheckout::log_download';

    const LOG_FILE = 'collectorbank.log';

    const ZIP_FILE = 'collectorbank_logs.zip';

    private FileFactory $fileFactory;

    private DirectoryList $directoryList;

    public function __construct(
        Action\Context $context,
        FileFactory $fileFactory,
        DirectoryList $directoryList
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->directoryList = $directoryList;
    }

    /**
     * @return ResponseInterface|void
     */
    public function execute()
    {
        try {
            $zipFilePath = sprintf(
                '%s/%s',
                $this->directoryList->getPath(DirectoryList::LOG),
                self::ZIP_FILE
            );
            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new LocalizedException(__('Unable to create zip archive.'));
            }

            $logFilePath = sprintf(
                '%s/%s',
                $this->directoryList->getPath(DirectoryList::LOG),
                self::LOG_FILE
            );
            $success = $zip->addFile($logFilePath, self::LOG_FILE);
            $zip->close();

            if (!$success) {
                throw new LocalizedException(__('No log files were found to download.'));
            }

            return $this->fileFactory->create(
                self::ZIP_FILE,
                [
                    'type' => 'filename',
                    'value' => $zipFilePath,
                    'rm' => true
                ],
                DirectoryList::VAR_DIR
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while trying to download the logs. Please try again later.'));
        }
        
        return $this->_redirect('admin/system_config/edit/section/payment');
    }
}
