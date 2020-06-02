<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Vsynch\LimitSales\Model\Attribute\Source;

class Duration extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * Get all options
     * @return array
     */
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = [
                ['label' => __('Select Duration'), 'value' => '0'],
                ['label' => __('1 Min'), 'value' => '60'],
                ['label' => __('1 Hour'), 'value' => '3600'],
                ['label' => __('1 Day'), 'value' => '86400'],
                ['label' => __('3 Days'), 'value' => '259200'],
                ['label' => __('5 Days'), 'value' => '432000'],
                ['label' => __('7 Days'), 'value' => '604800'],
                ['label' => __('14 Days'), 'value' => '1209600'],
                ['label' => __('30 Days'), 'value' => '2592000'],
                ['label' => __('Indefinite'), 'value' => '-1'],
            ];
        }
        return $this->_options;
    }
}
