<?php

declare(strict_types=1);

// JUser/View/Helper/UserWithIp.php

namespace JUser\View\Helper;

use Laminas\View\Helper\AbstractHelper;

use function sprintf;

class UserWithIp extends AbstractHelper
{
    public function __invoke(string $username, string $ipAddress): string
    {
        $record = $this->view->geoIp2City($ipAddress);
        $place  = '';
        if ($record?->city?->name) {
            $place .= $this->view->escapeHtml($record->city->name) . ", ";
        }
        if ($record?->country?->name) {
            $place .= $this->view->escapeHtml($record->country->name);
        }

        //if they didn't pass us a username print the place and/or ipAddress
        if ('' === $username) {
            if ('' === $place) {
                return $this->view->escapeHtml($ipAddress);
            } else {
                $return = '<span data-toggle="tooltip" title="%s">%s</span>';
                return sprintf($return, $this->view->escapeHtmlAttr($ipAddress), $place);
            }
        }

        //otherwise return the username with a tooltip
        if ('' === $place) {
            $tooltip = $ipAddress;
        } else {
            $tooltip = $place . ' (' . $this->view->escapeHtmlAttr($ipAddress) . ')';
        }
        $return = '<span data-toggle="tooltip" title="%s">%s</span>';
        return sprintf($return, $tooltip, $this->view->escapeHtml($username));
    }
}
