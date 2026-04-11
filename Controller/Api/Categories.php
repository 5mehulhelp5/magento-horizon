<?php

declare(strict_types=1);

namespace Byte8\Horizon\Controller\Api;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Byte8\Horizon\Model\Config;
use Byte8\Horizon\Model\Service\CategoryService;

class Categories extends AbstractAction
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        Config $config,
        private readonly CategoryService $categoryService
    ) {
        parent::__construct($request, $jsonFactory, $config);
    }

    protected function handleRequest(Json $result): Json
    {
        $parentId = $this->getStringParam('parent_id');
        $maxDepth = min($this->getIntParam('max_depth', 3), 5);

        $data = $this->categoryService->getTree(
            $parentId !== null ? (int) $parentId : null,
            $maxDepth
        );

        return $result->setData($data);
    }
}
