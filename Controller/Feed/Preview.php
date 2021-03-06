<?php
/**
 * Copyright © 2017 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Channable\Controller\Feed;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magmodules\Channable\Model\Generate as GenerateModel;
use Magmodules\Channable\Helper\General as GeneralHelper;
use Magmodules\Channable\Helper\Feed as FeedHelper;
use Magmodules\Channable\Helper\Preview as PreviewHelper;

class Preview extends Action
{

    /**
     * @var GenerateModel
     */
    private $generateModel;

    /**
     * @var GeneralHelper
     */
    private $generalHelper;

    /**
     * @var FeedHelper
     */
    private $feedHelper;

    /**
     * @var PreviewHelper
     */
    private $previewHelper;

    /**
     * Preview constructor.
     *
     * @param Context       $context
     * @param GeneralHelper $generalHelper
     * @param GenerateModel $generateModel
     * @param FeedHelper    $feedHelper
     * @param PreviewHelper $previewHelper
     */
    public function __construct(
        Context $context,
        GeneralHelper $generalHelper,
        GenerateModel $generateModel,
        FeedHelper $feedHelper,
        PreviewHelper $previewHelper
    ) {
        $this->generateModel = $generateModel;
        $this->generalHelper = $generalHelper;
        $this->feedHelper = $feedHelper;
        $this->previewHelper = $previewHelper;
        $this->resultFactory = $context->getResultFactory();
        parent::__construct($context);
    }

    /**
     * Execute function for preview of Channable debug feed
     */
    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('id');
        $page = (int)$this->getRequest()->getParam('page', 1);
        $token = $this->getRequest()->getParam('token');

        if (empty($storeId) || empty($token)) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setHeader('content-type', 'text/plain');
            $result->setContents(__('Params missing!'));

            return $result;
        }

        $enabled = $this->generalHelper->getEnabled($storeId);

        if (empty($enabled)) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setHeader('content-type', 'text/plain');
            $result->setContents(__('Please enable extension and flush cache!'));

            return $result;
        }

        if ($token != $this->generalHelper->getToken()) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setHeader('content-type', 'text/plain');
            $result->setContents(__('Token invalid!'));

            return $result;
        }

        if ($productId = $this->getRequest()->getParam('pid')) {
            $productId = [$productId];
        } else {
            $productId = '';
        }

        if ($feed = $this->generateModel->generateByStore($storeId, $page, $productId)) {
            $contents = $this->previewHelper->getPreviewData($feed, $storeId);
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setHeader('content-type', 'text/html');
            $result->setContents($contents);
            return $result;
        }

        return false;
    }
}
