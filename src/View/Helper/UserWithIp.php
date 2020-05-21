<?php

// JUser/View/Helper/UserWithIp.php

namespace JUser\View\Helper;

use Zend\View\Helper\AbstractHelper;

class UserWithIp extends AbstractHelper
{
    public function __invoke($username, $ipAddress)
    {
        $geo = $this->view->geoip($ipAddress);
        $place = '';
        if ($geo && $geo->getCity()) {
            $place .= $this->view->escapeHtml(mb_convert_encoding($geo->getCity(), "UTF-8", "ISO-8859-1")) . ", ";
        }
        if ($geo && $geo->getCountryName()) {
            $place .= $this->view->escapeHtml(mb_convert_encoding($geo->getCountryName(), "UTF-8", "ISO-8859-1"));
        }

        //if they didn't pass us a user name print the place and/or ipAddress
        if (is_null($username) || 0 == strlen($username)) {
            if ('' === $place) {
                return $ipAddress;
            } else {
                $return = '<span data-toggle="tooltip" title="%s">%s</span>';
                return sprintf($return, $ipAddress, $place);
            }
        }
        //otherwise return the username with a tooltip
        if ('' === $place) {
            $tooltip = $ipAddress;
        } else {
            $tooltip = $place . ' (' . $ipAddress . ')';
        }
        $return = '<span data-toggle="tooltip" title="%s">%s</span>';
        return sprintf($return, $tooltip, $username);
    }
}
