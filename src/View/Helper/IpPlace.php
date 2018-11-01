<?php
// JUser/View/Helper/Email.php

namespace JUser\View\Helper;

use Zend\View\Helper\AbstractHelper;

class IpPlace extends AbstractHelper
{
    public function __invoke($ipAddress)
    {
        $geo = $this->view->geoip($ipAddress);
        $return = '';
        //@todo this has some encoding problems
        if ($geo&&$geo->getCity()) {
            $return .= $this->view->escapeHtml(mb_convert_encoding($geo->getCity(), "UTF-8", "auto")).", ";
        }
        if ($geo&&$geo->getCountryName()) {
            $return .= $this->view->escapeHtml(mb_convert_encoding($geo->getCountryName(), "UTF-8", "auto"));
        }
        if ('' === $return) {
            return $ipAddress;
        }
        $tooltip = '<span data-toggle="tooltip" title="%s">%s</span>';
        return sprintf($tooltip, $ipAddress, $return);
    }
}
