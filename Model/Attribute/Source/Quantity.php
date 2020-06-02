<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Vsynch\LimitSales\Model\Attribute\Source;

class Quantity extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * Get all options
     * @return array
     */
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = [
                ['label' => __('Select Limit'), 'value' => '0'],
                ['label' => __('1'), 'value' => '1'],
                ['label' => __('3'), 'value' => '3'],
                ['label' => __('5'), 'value' => '5'],
                ['label' => __('10'), 'value' => '10'],
                ['label' => __('20'), 'value' => '20'],
                ['label' => __('30'), 'value' => '30'],
            ];
        }
        return $this->_options;
    }
}
