<?php

// JUser/View/Helper/IpPlace.php

namespace JUser\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class IpPlace extends AbstractHelper
{
    public function __invoke($ipAddress)
    {
        //IP geolocation was removed on purpose; display the plain address
        return $this->view->escapeHtml($ipAddress);
    }
}
