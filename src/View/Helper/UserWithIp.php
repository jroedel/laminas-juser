<?php

// JUser/View/Helper/UserWithIp.php

namespace JUser\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class UserWithIp extends AbstractHelper
{
    public function __invoke($username, $ipAddress)
    {
        //IP geolocation was removed on purpose; the tooltip carries only the address
        if (is_null($username) || 0 == strlen($username)) {
            return $this->view->escapeHtml($ipAddress);
        }
        $return = '<span data-toggle="tooltip" title="%s">%s</span>';
        return sprintf(
            $return,
            $this->view->escapeHtmlAttr($ipAddress),
            $this->view->escapeHtml($username)
        );
    }
}
