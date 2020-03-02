<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\ThankYouPageGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\Argument\SearchCriteria\Builder as SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\ThankYouPageRepository;

/**
 * Class ThankYouPage
 * @package Mageplaza\ThankYouPageGraphQl\Model\Resolver
 */
class ThankYouPage implements ResolverInterface
{

    /**
     * @var Data
     */
    protected $helperData;
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var ThankYouPageRepository
     */
    protected $thankYouPageRepository;

    /**
     * ThankYouPage constructor.
     *
     * @param Data $helperData
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ThankYouPageRepository $thankYouPageRepository
     */
    public function __construct(
        Data $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ThankYouPageRepository $thankYouPageRepository
    ) {
        $this->helperData = $helperData;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->thankYouPageRepository = $thankYouPageRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!$this->helperData->isEnabled()) {
            throw new GraphQlInputException(__('The module is disabled'));
        }
        if ($args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }
        if ($args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }

        $searchCriteria = $this->searchCriteriaBuilder->build($field->getName(), $args);
        $searchCriteria->setCurrentPage($args['currentPage']);
        $searchCriteria->setPageSize($args['pageSize']);
        $searchResult = $this->thankYouPageRepository->getList($searchCriteria);
        $result = [];
        foreach ($searchResult->getItems() as $item) {
            $templateId = $item->getTemplateId();
            $result[$templateId] = $item->getData();
            $result[$templateId]['condition'] = $this->thankYouPageRepository->dataModelToArray($item->getCondition());
            $result[$templateId]['model'] = $item;
        }
        $collectionSize = $searchResult->getTotalCount();
        //possible division by 0
        $pageSize = $searchResult->getSearchCriteria()->getPageSize();
        if ($pageSize) {
            $maxPages = ceil($collectionSize / $searchCriteria->getPageSize());
        } else {
            $maxPages = 0;
        }

        $currentPage = $searchResult->getSearchCriteria()->getCurrentPage();
        if ($collectionSize > 0 && $searchCriteria->getCurrentPage() > $maxPages) {
            throw new GraphQlInputException(
                __(
                    'currentPage value %1 specified is greater than the %2 page(s) available.',
                    [$currentPage, $maxPages]
                )
            );
        }

        return [
            'total_count' => $collectionSize,
            'items'       => $result,
            'page_info'   => [
                'page_size'    => $pageSize,
                'current_page' => $currentPage,
                'total_pages'  => $maxPages
            ]
        ];
    }
}
