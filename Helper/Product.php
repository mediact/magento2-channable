<?php
/**
 * Copyright © 2016 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Channable\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Filter\FilterManager;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;

use Magmodules\Channable\Helper\General as GeneralHelper;

class Product extends AbstractHelper
{

    protected $general;
    protected $source;
    protected $imgHelper;
    protected $eavConfig;
    protected $filter;
    protected $catalogProductTypeConfigurable;

    /**
     * Product constructor.
     * @param Context $context
     * @param GalleryReadHandler $galleryReadHandler
     * @param General $general
     * @param EavConfig $eavConfig
     * @param FilterManager $filter
     * @param Configurable $catalogProductTypeConfigurable
     */
    public function __construct(
        Context $context,
        GalleryReadHandler $galleryReadHandler,
        GeneralHelper $general,
        EavConfig $eavConfig,
        FilterManager $filter,
        Configurable $catalogProductTypeConfigurable
    ) {
        $this->galleryReadHandler = $galleryReadHandler;
        $this->general = $general;
        $this->eavConfig = $eavConfig;
        $this->filter = $filter;
        $this->catalogProductTypeConfigurable = $catalogProductTypeConfigurable;
        parent::__construct($context);
    }

    /**
     * @param $product
     * @param $parent
     * @param $config
     * @return array
     */
    public function getDataRow($product, $parent, $config)
    {
        $dataRow = [];

        if (!$this->validateProduct($product, $parent, $config)) {
            return $dataRow;
        }

        foreach ($config['attributes'] as $type => $attribute) {
            $simple = '';
            $productData = $product;
            if ($attribute['parent'] && $parent) {
                $productData = $parent;
                $simple = $product;
            }
            if (($attribute['parent'] == 2) && !$parent) {
                continue;
            }
            if (!empty($attribute['source']) || ($attribute['label'] == 'image_link')) {
                $dataRow[$attribute['label']] = $this->getAttributeValue(
                    $type,
                    $attribute,
                    $config,
                    $productData,
                    $simple
                );
            }
            if (!empty($attribute['static'])) {
                $dataRow[$attribute['label']] = $attribute['static'];
            }
            if (!empty($attribute['condition'])) {
                $dataRow[$attribute['label']] = $this->getCondition(
                    $dataRow[$attribute['label']],
                    $attribute['condition']
                );
            }
            if (!empty($attribute['collection'])) {
                if ($dataCollection = $this->getAttributeCollection($type, $config, $productData)) {
                    $dataRow = array_merge($dataRow, $dataCollection);
                }
            }
        }
                
        return $dataRow;
    }

    /**
     * @param $type
     * @param $attribute
     * @param $config
     * @param $product
     * @param $simple
     * @return mixed|string
     */
    public function getAttributeValue($type, $attribute, $config, $product, $simple)
    {
        switch ($type) {
            case 'link':
                $value = $this->getProductUrl($product, $simple, $config);
                break;
            case 'image_link':
                $value = $this->getImage($attribute, $config, $product);
                break;
            case 'manage_stock':
            case 'min_sale_qty':
            case 'qty_increments':
                $value = $this->getStockValue($type, $product, $config['inventory']);
                break;
            default:
                $value = $this->getValue($attribute, $product);
                break;
        }

        if (!empty($value)) {
            if (!empty($attribute['actions']) || !empty($attribute['max'])) {
                $value = $this->getFormat($value, $attribute);
            }
            if (!empty($attribute['suffix'])) {
                if (!empty($config[$attribute['suffix']])) {
                    $value .= $config[$attribute['suffix']];
                }
            }
        } else {
            if (!empty($attribute['default'])) {
                $value = $attribute['default'];
            }
        }
        return $value;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\Product $parent
     * @param $config
     * @return bool
     */
    public function validateProduct($product, $parent, $config)
    {
        $filters = $config['filters'];
        if (!empty($filters['exclude_parent'])) {
            if ($product->getTypeId() == 'configurable') {
                return false;
            }
        }
        if (!empty($parent)) {
            if ($parent->getStatus() == 2) {
                return false;
            }
            if ($parent->getVisibility() == 1) {
                return false;
            }
            if (!empty($filters['stock'])) {
                if ($parent->getIsInStock() == 0) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param $attributes
     * @param string $parentAttributes
     * @return array
     */
    public function addAttributeData($attributes, $parentAttributes)
    {
        foreach ($attributes as $key => $value) {
            if (!empty($value['source'])) {
                $attribute = $this->eavConfig->getAttribute('catalog_product', $value['source']);
                $frontendInput = $attribute->getFrontendInput();
                $attributes[$key]['type'] = $frontendInput;
            }
            if (in_array($key, $parentAttributes)) {
                $attributes[$key]['parent'] = 1;
            } else {
                $parent = (!empty($value['parent']) ? $value['parent'] : 0);
                $attributes[$key]['parent'] = $parent;
            }
        }

        return $attributes;
    }

    /**
     * @param $type
     * @param $config
     * @param $product
     * @return array
     */
    public function getAttributeCollection($type, $config, $product)
    {
        if ($type == 'price') {
            return $this->getPriceCollection($config, $product);
        }

        return [];
    }

    /**
     * @param $attribute
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    public function getValue($attribute, $product)
    {

        if ($attribute['type'] == 'select') {
            if ($attr = $product->getResource()->getAttribute($attribute['source'])) {
                $value = $product->getData($attribute['source']);
                return (string)$attr->getSource()->getOptionText($value);
            }
        }
        if ($attribute['type'] == 'multiselect') {
            if ($attr = $product->getResource()->getAttribute($attribute['source'])) {
                $value_text = [];
                $values = explode(',', $product->getData($attribute['source']));
                foreach ($values as $value) {
                    $value_text[] = $attr->getSource()->getOptionText($value);
                }
                return implode('/', $value_text);
            }
        }
        
        return $product->getData($attribute['source']);
    }

    /**
     * @param $attribute
     * @param $product
     * @param $inventory
     * @return bool
     */
    public function getStockValue($attribute, $product, $inventory)
    {
        if ($attribute == 'manage_stock') {
            if ($product->getData('use_config_manage_stock')) {
                return $inventory['config_manage_stock'];
            } else {
                return $product->getData('manage_stock');
            }
        }
        if ($attribute == 'min_sale_qty') {
            if ($product->getData('use_config_min_sale_qty')) {
                return $inventory['config_min_sale_qty'];
            } else {
                return $product->getData('min_sale_qty');
            }
        }
        if ($attribute == 'qty_increments') {
            if ($product->getData('use_config_enable_qty_inc')) {
                if (!$inventory['config_enable_qty_inc']) {
                    return false;
                }
            } else {
                if (!$product->getData('enable_qty_inc')) {
                    return false;
                }
            }
            if ($product->getData('use_config_qty_increments')) {
                return $inventory['config_qty_increments'];
            } else {
                return $product->getData('qty_increments');
            }
        }
    }

    /**
     * @param $config
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function getPriceCollection($config, $product)
    {

        $price = '';
        $prices = [];
        $config = $config['price_config'];
        
        if ($product->getPrice() > 0) {
            $price = $this->formatPrice($product->getPrice(), $config);
        } else {
            if ($product->getFinalPrice() > 0) {
                $price = $this->formatPrice($product->getFinalPrice(), $config);
            } else {
                $price = $this->formatPrice($product->getMinimalPrice(), $config);
            }
        }
        
        if (!empty($price)) {
            $prices[$config['price']] = $price;
        } else {
            return $prices;
        }
        
        if ($product->getSpecialPrice() > 0) {
            if ($product->getSpecialPrice() < $price) {
                $prices[$config['sales_price']] = $this->formatPrice($product->getSpecialPrice(), $config);
                if ($product->getSpecialFromDate() && $product->getSpecialToDate()) {
                    $from = date('Y-m-d', strtotime($product->getSpecialFromDate()));
                    $to = date('Y-m-d', strtotime($product->getSpecialToDate()));
                    $prices[$config['sales_date_range']] = $from . '/' . $to;
                }
            }
        }
         
        return $prices;
    }

    /**
     * @param $value
     * @param $conditions
     * @return bool
     */
    public function getCondition($value, $conditions)
    {
        $data = '';
        foreach ($conditions as $condition) {
            $ex = explode(':', $condition);
            if ($ex['0'] == '*') {
                $data = $ex['1'];
            }
            if ($value == $ex['0']) {
                $data = $ex[1];
            }
        }

        return $data;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\Product $simple
     * @param $config
     * @return string
     */
    public function getProductUrl($product, $simple, $config)
    {
        $url = $product->getUrlModel()->getUrl($product);
        if (!empty($config['utm_code'])) {
            $url .= $config['utm_code'];
        }
        if (!empty($simple)) {
            if ($product->getTypeId() == 'configurable') {
                $options = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
                foreach ($options as $option) {
                    if ($id = $simple->getResource()->getAttributeRawValue(
                        $simple->getId(),
                        $option['attribute_code'],
                        $config['store_id']
                    )
                    ) {
                        $url_extra[] = $option['attribute_id'] . '=' . $id;
                    }
                }
            }
            if (!empty($url_extra)) {
                $url = $url . '#' . implode('&', $url_extra);
            }
        }
                
        return $url;
    }

    /**
     * @param $value
     * @param string $actions
     * @param string $max
     * @return mixed|string
     */
    public function getFormat($value, $attribute)
    {
        if (!empty($attribute['actions'])) {
            $actions = $attribute['actions'];
            if (in_array('striptags', $actions)) {
                $value = str_replace(["\r", "\n"], "", $value);
                $value = strip_tags($value);
            }
            if (in_array('number', $actions)) {
                $value = number_format($value, 2);
            }
        }
        if (!empty($attribute['max'])) {
            $value =  $this->filter->truncate($value, ['length' => $attribute['max']]);
        }
        return $value;
    }

    /**
     * @param $attribute
     * @param $config
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    public function getImage($attribute, $config, $product)
    {
        if (empty($attribute['source'])) {
            $images = [];
            $this->galleryReadHandler->execute($product);
            $galleryImages = $product->getMediaGalleryImages();
        
            foreach ($galleryImages as $image) {
                if (empty($image['disabled'])) {
                    $images[] = $image['url'];
                }
            }

            return $images;
        } else {
            $img = '';
            if ($url = $product->getData($attribute['source'])) {
                $img = $config['url_type_media'] . 'catalog/product' . $url;
            }

            return $img;
        }
    }

    /**
     * @param $data
     * @param $config
     * @return string
     */
    public function formatPrice($data, $config)
    {
        return number_format($data, 2, '.', '') . $config['currency'];
    }

    /**
     * @param $productId
     * @return bool
     */
    public function getParentId($productId)
    {
        $parentByChild = $this->catalogProductTypeConfigurable->getParentIdsByChild($productId);
        if (isset($parentByChild[0])) {
            $id = $parentByChild[0];
            return $id;
        }
        return false;
    }
}