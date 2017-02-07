<?php
namespace JUser\Model;

/**
 * Interface for person value options provider
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
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
