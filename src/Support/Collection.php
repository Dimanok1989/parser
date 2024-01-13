<?php

namespace Kolgaev\Parser\Support;

use Illuminate\Support\Collection as SupportCollection;

class Collection extends SupportCollection
{
    /**
     * Get an item from the collection by key.
     *
     * @param  string  $key
     * @return TValue|TGetDefault
     */
    public function __get($key)
    {
        return $this->get($key);
    }
}