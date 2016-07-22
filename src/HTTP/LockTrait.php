<?php
namespace trident\HTTP;


trait LockTrait
{
    protected $isLocked = false;

    public function lock()
    {
        $this->isLocked = true;
    }
} 