<?php

namespace JUser\Model;

/**
 * Interface for person value options provider
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
interface PersonValueOptionsProviderInterface
{

    /**
     * Gets a simple key => value array of personId => name
     * @param bool $includeInactive
     */
    public function getPersonValueOptions($includeInactive = false);

    public function getPersons();
}
